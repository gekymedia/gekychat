# 🔐 Two-Factor Authentication (2FA) Setup Guide

## Overview

Two-factor authentication adds an extra layer of security for users who enable it. After phone login, users with 2FA enabled will be required to enter a 6-digit code sent to their email before accessing the app.

## How It Works

1. **User enables 2FA** in Settings → Account → Two-factor authentication
2. **User must have an email** configured (if not, they'll be prompted to add one)
3. **After phone login**, if 2FA is enabled:
   - A 6-digit code is generated and sent to their email
   - User is redirected to 2FA verification page
   - User enters the code to complete login
4. **Code expires** after 10 minutes
5. **User can resend** the code if needed

---

## Implementation Status

✅ **Completed:**
- 2FA toggle in Settings UI
- 2FA code generation methods in User model
- 2FA verification controller
- 2FA verification view
- Email sending infrastructure

⚠️ **Needs Implementation:**
- Check 2FA status after phone login
- Generate and send 2FA code when needed
- Redirect to 2FA page if enabled
- Email sending for 2FA codes

---

## Step 1: Verify User Model Has 2FA Methods

Check that `app/Models/User.php` has these methods:

```php
public function generateTwoFactorCode(): string
{
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
    $this->update([
        'two_factor_code' => $code,
        'two_factor_expires_at' => now()->addMinutes(10),
    ]);
    
    return $code;
}

public function clearTwoFactorCode(): void
{
    $this->update([
        'two_factor_code' => null,
        'two_factor_expires_at' => null,
    ]);
}

public function hasTwoFactorEnabled(): bool
{
    $settings = json_decode($this->settings ?? '{}', true);
    return $settings['account']['two_factor_enabled'] ?? false;
}

public function requiresTwoFactor(): bool
{
    return $this->hasTwoFactorEnabled() && !empty($this->email);
}
```

---

## Step 2: Update Phone Login Flow

In `app/Http/Controllers/Auth/PhoneVerificationController.php`, after successful OTP verification:

```php
public function verifyOtp(Request $request)
{
    // ... existing OTP verification code ...
    
    // Log in the user
    Auth::login($user, $request->remember ?? false);
    
    // Check if 2FA is enabled
    if ($user->requiresTwoFactor()) {
        // Generate 2FA code
        $code = $user->generateTwoFactorCode();
        
        // Send email with 2FA code
        // TODO: Implement email sending
        // Mail::to($user->email)->send(new TwoFactorCodeMail($code));
        
        // Redirect to 2FA verification
        return redirect()->route('verify.2fa')
            ->with('status', 'We\'ve sent a verification code to your email.');
    }
    
    // ... rest of existing code ...
    return redirect()->route('chat.index')->with('success', 'Login successful!');
}
```

---

## Step 3: Create Email Template for 2FA Code

Create `app/Mail/TwoFactorCodeMail.php`:

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TwoFactorCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function build()
    {
        return $this->subject('Your GekyChat Verification Code')
            ->view('emails.two-factor-code')
            ->with(['code' => $this->code]);
    }
}
```

Create `resources/views/emails/two-factor-code.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Verification Code</title>
</head>
<body>
    <h2>Your Verification Code</h2>
    <p>Your GekyChat verification code is:</p>
    <h1 style="font-size: 32px; letter-spacing: 8px; text-align: center; padding: 20px; background: #f0f0f0; border-radius: 8px;">
        {{ $code }}
    </h1>
    <p>This code will expire in 10 minutes.</p>
    <p>If you didn't request this code, please ignore this email.</p>
</body>
</html>
```

---

## Step 4: Update Settings Controller to Require Email for 2FA

In `app/Http/Controllers/SettingsController.php`:

```php
public function update(Request $request)
{
    // ... existing validation ...
    
    // Handle 2FA toggle
    if (isset($data['account']['two_factor_enabled'])) {
        $user = Auth::user();
        $settings = json_decode($user->settings ?? '{}', true);
        
        // If enabling 2FA, require email
        if ($data['account']['two_factor_enabled'] && empty($user->email)) {
            return back()->withErrors([
                'account.two_factor_enabled' => 'Please add an email address in Security settings before enabling 2FA.'
            ]);
        }
        
        $settings['account']['two_factor_enabled'] = $data['account']['two_factor_enabled'];
        $user->settings = json_encode($settings);
        $user->save();
    }
    
    // ... rest of update logic ...
}
```

---

## Step 5: Test the Flow

1. **Add email** in Settings → Security
2. **Enable 2FA** in Settings → Account
3. **Logout** and login again with phone
4. **Check email** for 2FA code
5. **Enter code** on verification page
6. **Verify** you're logged in

---

## API Integration (Mobile App)

For mobile app, the flow should be:

1. **POST /api/v1/auth/login** with phone + OTP
2. **Response includes:** `requires_two_factor: true/false`
3. **If true:** Mobile app shows 2FA input screen
4. **POST /api/v1/auth/verify-2fa** with code
5. **Response includes:** Access token

Update `app/Http/Controllers/Api/V1/AuthController.php` accordingly.

---

## Security Considerations

1. ✅ **Code expires** after 10 minutes
2. ✅ **Rate limiting** on 2FA endpoints (already implemented)
3. ⚠️ **Email delivery** should be reliable (use queue for production)
4. ⚠️ **Log 2FA attempts** for security monitoring
5. ⚠️ **Consider backup codes** for users who lose email access

---

## Next Steps

1. Implement email sending for 2FA codes
2. Update phone login to check 2FA status
3. Add 2FA verification to API endpoints
4. Test the complete flow
5. Add email verification if not already implemented
