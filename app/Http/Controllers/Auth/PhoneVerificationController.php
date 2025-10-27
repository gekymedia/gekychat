<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SmsServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PhoneVerificationController extends Controller
{
    protected SmsServiceInterface $smsService;
    protected int $maxAttempts = 3;
    protected int $decayMinutes = 5;

    public function __construct(SmsServiceInterface $smsService)
    {
        $this->smsService = $smsService;
    }

    public function show()
    {
        return view('auth.phone-login');
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|digits:10|regex:/^0[0-9]{9}$/' // Ghanaian phone format
        ]);

        $throttleKey = 'otp:' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, $this->maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'phone' => "Too many attempts. Please try again in {$seconds} seconds."
            ]);
        }

        RateLimiter::hit($throttleKey, $this->decayMinutes * 60);

        $user = User::firstOrCreate(
            ['phone' => $request->phone],
            [
                'name' => 'User_' . substr($request->phone, -4),
                'email' => 'user_' . $request->phone . '@example.com',
                'password' => bcrypt(uniqid()) // Temporary password
            ]
        );

        $otp = rand(100000, 999999);
        $user->update([
            'otp_code' => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(5),
        ]);

        // Send OTP via SMS
        $message = "Your OTP code is: {$otp}. Valid for 5 minutes.";
        $smsResponse = $this->smsService->sendSms($request->phone, $message);

        if (!$smsResponse['success']) {
            return back()->withErrors([
                'phone' => 'Failed to send OTP. Please try again later.'
            ]);
        }

        session([
            'otp_user_id' => $user->id,
            'phone' => $request->phone,
            'resend_time' => Carbon::now()->addSeconds(30)->timestamp
        ]);

        return redirect()->route('verify.otp')->with([
            'status' => 'OTP sent to your phone number',
            'sms_balance' => $smsResponse['balance']
        ]);
    }

    public function showOtpForm()
    {
        if (!session('phone')) {
            return redirect()->route('login');
        }

        return view('auth.verify-otp', [
            'phone' => session('phone'),
            'resend_time' => session('resend_time')
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp_code' => 'required|digits:6' // Match the form field name
        ]);

        $user = User::where('phone', session('phone'))
            ->where('otp_code', $request->otp_code)
            ->where('otp_expires_at', '>', Carbon::now())
            ->first();

        if (!$user) {
            return back()->withErrors(['otp_code' => 'Invalid or expired OTP code.']);
        }

        // Clear OTP and mark as verified
        $user->update([
            'otp_code' => null,
            'otp_expires_at' => null,
            'phone_verified_at' => Carbon::now()
        ]);

        // Log in the user
        Auth::login($user, $request->remember ?? false);

        // Clear session data
        session()->forget(['otp_user_id', 'phone', 'resend_time']);

        // Redirect to chat
        return redirect()->route('chat.index')->with('success', 'Login successful!');
    }

    public function resendOtp(Request $request)
    {
        if (!session('otp_user_id')) {
            return redirect()->route('login');
        }

        $user = User::find(session('otp_user_id'));
        if (!$user) {
            return redirect()->route('login');
        }

        $otp = rand(100000, 999999);
        $user->update([
            'otp_code' => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(5),
        ]);

        $message = "Your new OTP code is: {$otp}. Valid for 5 minutes.";
        $smsResponse = $this->smsService->sendSms($user->phone, $message);

        if (!$smsResponse['success']) {
            return back()->withErrors([
                'otp_code' => 'Failed to resend OTP. Please try again.'
            ]);
        }

        // Update resend time in session
        session(['resend_time' => Carbon::now()->addSeconds(30)->timestamp]);

        return back()->with([
            'status' => 'New OTP sent to your phone',
            'sms_balance' => $smsResponse['balance']
        ]);
    }
}