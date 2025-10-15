<?php
// app/Http/Controllers/Auth/TwoFactorController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function show()
    {
        return view('auth.verify-2fa');
    }

    public function verify(Request $request)
    {
        $request->validate(['two_factor_code' => 'required|digits:6']);

        $user = Auth::user();

        if ($request->two_factor_code !== $user->two_factor_code) {
            throw ValidationException::withMessages([
                'two_factor_code' => 'Invalid verification code.'
            ]);
        }

        if (now()->gt($user->two_factor_expires_at)) {
            Auth::logout();
            return redirect('/login')->withErrors([
                'two_factor_code' => 'Code expired. Please login again.'
            ]);
        }

        $user->clearTwoFactorCode();
        return redirect()->intended('/chat');
    }

    public function resend()
    {
        $user = Auth::user();
        $user->generateTwoFactorCode();
        // Here you would resend the email with the new 2FA code
        
        return back()->with('status', 'New verification code sent to your email.');
    }
}