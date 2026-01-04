<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    /**
     * Generate QR code and secret for enabling 2FA.
     * GET /two-factor/setup
     */
    public function setup(Request $request)
    {
        $user = $request->user();

        if ($user->two_factor_enabled) {
            return response()->json([
                'error' => 'Two-factor authentication is already enabled',
            ], 400);
        }

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        
        // Generate recovery codes
        $recoveryCodes = collect(range(1, 8))->map(function () {
            return Str::random(10);
        })->all();

        // Generate QR code URL
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name', 'GekyChat'),
            $user->email ?? $user->phone,
            $secret
        );

        // Store temporarily (not enabled yet)
        $user->two_factor_secret = encrypt($secret);
        $user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        $user->save();

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'recovery_codes' => $recoveryCodes,
            'message' => 'Verify with a code to enable 2FA',
        ]);
    }

    /**
     * Enable 2FA after verification.
     * POST /two-factor/enable
     */
    public function enable(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($user->two_factor_enabled) {
            return response()->json([
                'error' => 'Two-factor authentication is already enabled',
            ], 400);
        }

        if (!$user->two_factor_secret) {
            return response()->json([
                'error' => 'Please setup 2FA first',
            ], 400);
        }

        $google2fa = new Google2FA();
        $secret = decrypt($user->two_factor_secret);

        $valid = $google2fa->verifyKey($secret, $request->code, 2); // 2 = 60 second window

        if (!$valid) {
            return response()->json([
                'error' => 'Invalid verification code',
            ], 400);
        }

        $user->two_factor_enabled = true;
        $user->save();

        return response()->json([
            'message' => 'Two-factor authentication enabled successfully',
            'recovery_codes' => json_decode(decrypt($user->two_factor_recovery_codes)),
        ]);
    }

    /**
     * Disable 2FA.
     * POST /two-factor/disable
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user->two_factor_enabled) {
            return response()->json([
                'error' => 'Two-factor authentication is not enabled',
            ], 400);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => 'Invalid password',
            ], 400);
        }

        $user->two_factor_enabled = false;
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->save();

        return response()->json([
            'message' => 'Two-factor authentication disabled successfully',
        ]);
    }

    /**
     * Get 2FA status.
     * GET /two-factor/status
     */
    public function status(Request $request)
    {
        $user = $request->user();

        $recoveryCodes = null;
        if ($user->two_factor_recovery_codes) {
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes));
        }

        return response()->json([
            'enabled' => $user->two_factor_enabled ?? false,
            'recovery_codes_count' => $recoveryCodes ? count($recoveryCodes) : 0,
        ]);
    }

    /**
     * Regenerate recovery codes.
     * POST /two-factor/regenerate-recovery-codes
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user->two_factor_enabled) {
            return response()->json([
                'error' => 'Two-factor authentication is not enabled',
            ], 400);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => 'Invalid password',
            ], 400);
        }

        $recoveryCodes = collect(range(1, 8))->map(function () {
            return Str::random(10);
        })->all();

        $user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        $user->save();

        return response()->json([
            'recovery_codes' => $recoveryCodes,
            'message' => 'Recovery codes regenerated successfully',
        ]);
    }
}

