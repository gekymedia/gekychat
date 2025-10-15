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
            'phone' => 'required|regex:/^0[0-9]{9}$/'
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
                'name'     => 'User_' . substr($request->phone, -4),
                'password' => bcrypt(Str::random(16)),
            ]
        );

        // Ensure a string OTP is passed to the SMS service
        $otp = (string) $user->generateOtp();

        try {
            $smsResult = $this->smsService->sendOtp($user->phone, $otp);

            if (!($smsResult['success'] ?? false)) {
                throw new \Exception($smsResult['error'] ?? 'Failed to send OTP');
            }

            return redirect()->route('verify.otp')->with([
                'phone'       => $request->phone,
                'status'      => 'OTP sent successfully',
                'resend_time' => now()->addMinutes(1)->timestamp,
            ]);

        } catch (\Exception $e) {
            RateLimiter::clear($throttleKey);

            throw ValidationException::withMessages([
                'phone' => $e->getMessage()
            ]);
        }
    }

    public function showOtpForm()
    {
        if (!session('phone')) {
            return redirect()->route('login');
        }

        return view('auth.verify-otp', [
            'resend_time' => session('resend_time')
        ]);
    }

    public function verifyOtp(Request $request)
{
    $request->validate([
        'otp'   => 'required|digits:6',
        'phone' => 'required'
    ]);

    $user = User::where('phone', $request->phone)
        ->where('otp_code', $request->otp)
        ->where('otp_expires_at', '>', now())
        ->first();

    if (!$user) {
        throw ValidationException::withMessages([
            'otp' => 'Invalid or expired OTP code.'
        ]);
    }

    // Mark verified & clear OTP
    $user->markPhoneAsVerified();
    $user->clearOtp();

    // ⭐ Log in on the *web* guard, rotate session, and set remember cookie
    Auth::guard('web')->login($user, remember: true);
    $request->session()->regenerate(); // (or $request->session()->migrate(true))

    // If you really have a 2FA email step, keep this branch;
    // otherwise remove it so you don’t get redirected away.
    if ($user->email) {
        $user->generateTwoFactorCode();
        return redirect()->route('verify.2fa');
    }

    // Use a named route or absolute path but keep same host.
    return redirect()->intended(route('chat.index'));
}


    public function resendOtp(Request $request)
    {
        $request->validate(['phone' => 'required']);

        $throttleKey = 'otp-resend:' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 2)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'phone' => "Please wait {$seconds} seconds before requesting another OTP."
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        $user = User::where('phone', $request->phone)->firstOrFail();

        // Ensure a string OTP is passed to the SMS service
        $otp = (string) $user->generateOtp();

        $smsResult = $this->smsService->sendOtp($user->phone, $otp);

        if (!($smsResult['success'] ?? false)) {
            throw ValidationException::withMessages([
                'phone' => $smsResult['error'] ?? 'Failed to resend OTP'
            ]);
        }

        return back()->with([
            'status'      => 'New OTP sent successfully',
            'resend_time' => now()->addMinutes(1)->timestamp
        ]);
    }
}
