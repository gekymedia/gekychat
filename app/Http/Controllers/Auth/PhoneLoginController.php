<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ArkeselSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PhoneLoginController extends Controller
{
    protected $smsService;

    public function __construct(ArkeselSmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    public function showPhoneForm()
    {
        return view('auth.phone-login');
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|digits:10|regex:/^0[0-9]{9}$/' // Ghanaian phone format
        ]);

        $otp = rand(100000, 999999);
        $user = User::firstOrCreate(
            ['phone' => $request->phone],
            [
                'name' => 'User_' . substr($request->phone, -4),
                'email' => 'user_' . $request->phone . '@example.com',
                'password' => bcrypt(uniqid()) // Temporary password
            ]
        );

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

        session(['otp_user_id' => $user->id]);
        return redirect()->route('verify.otp')->with([
            'success' => 'OTP sent to your phone number',
            'sms_balance' => $smsResponse['balance'] // Optional: for admin tracking
        ]);
    }

    public function showOtpForm()
    {
        if (!session('otp_user_id')) {
            return redirect()->route('phone.login')->withErrors([
                'phone' => 'Please request a new OTP'
            ]);
        }

        return view('auth.verify-otp');
    }

public function verifyOtp(Request $request)
{
    $request->validate(['otp_code' => 'required|digits:6']);

    $user = User::find(session('otp_user_id'));

    if (!$user) {
        return redirect()->route('phone.login')->withErrors([
            'phone' => 'Session expired. Please request a new OTP.'
        ]);
    }

    if (Carbon::now()->gt($user->otp_expires_at)) {
        return back()->withErrors(['otp_code' => 'OTP has expired']);
    }

    if ($user->otp_code !== $request->otp_code) {
        return back()->withErrors(['otp_code' => 'Invalid OTP code']);
    }

    // Clear OTP and login
    $user->update([
        'otp_code' => null,
        'otp_expires_at' => null,
        'phone_verified_at' => Carbon::now()
    ]);

    Auth::login($user, $request->remember ?? false);

    // ✅ SIMPLIFIED: Just redirect - session auth will handle everything
    return redirect()->intended('/chat')->with('success', 'Login successful!');
}

    public function resendOtp(Request $request)
    {
        if (!session('otp_user_id')) {
            return redirect()->route('phone.login');
        }

        $user = User::find(session('otp_user_id'));
        if (!$user) {
            return redirect()->route('phone.login');
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

        return back()->with([
            'success' => 'New OTP sent to your phone',
            'sms_balance' => $smsResponse['balance']
        ]);
    }
}