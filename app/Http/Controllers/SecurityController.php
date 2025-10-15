<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class SecurityController extends Controller
{
    public function show()
    {
        return view('settings.security');
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'email'    => ['nullable','email', Rule::unique('users','email')->ignore($user->id)],
            'password' => ['nullable','string','min:8','confirmed'], // uses password + password_confirmation
        ]);

        // Update email if provided
        if (array_key_exists('email', $data)) {
            $user->email = $data['email'] ?: null;
        }

        // Update password if provided
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        // If you want to trigger 2FA via email after they add an email:
        // if ($user->email) {
        //     $user->generateTwoFactorCode();
        //     // Send email with $user->two_factor_code here...
        //     return redirect()->route('verify.2fa')->with('status', 'We emailed you a verification code.');
        // }

        return back()->with('status', 'Security settings updated.');
    }
}
