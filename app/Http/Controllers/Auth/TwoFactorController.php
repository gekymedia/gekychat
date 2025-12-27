<?php
// app/Http/Controllers/Auth/TwoFactorController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\TwoFactorCodeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function show()
    {
        return view('auth.verify-2fa');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'two_factor_pin' => 'required|digits:6'
        ]);

        $user = Auth::user();

        // Verify the PIN
        if (!$user->verifyTwoFactorPin($request->two_factor_pin)) {
            throw ValidationException::withMessages([
                'two_factor_pin' => 'Incorrect PIN. Please try again.'
            ]);
        }

        // PIN verified successfully - redirect to intended page
        return redirect()->intended('/chat')->with('success', 'Login successful!');
    }
}