<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\ArkeselSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private ArkeselSmsService $sms) {}

    /**
     * Request OTP
     * POST /api/v1/auth/phone
     */
    public function requestOtp(Request $r)
    {
        $r->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $phone = preg_replace('/\D+/', '', $r->phone);

        // Rate limiting: Temporarily disabled for testing
        // TODO: Re-enable rate limiting after testing
        // if (!OtpCode::canRequest($phone, 3)) {
        //     // Calculate when the user can try again
        //     $oldestRequest = OtpCode::where('phone', $phone)
        //         ->where('created_at', '>', now()->subHour())
        //         ->orderBy('created_at', 'asc')
        //         ->first();
        //     
        //     $waitMinutes = 60; // Default to 60 minutes
        //     if ($oldestRequest) {
        //         $waitUntil = $oldestRequest->created_at->copy()->addHour();
        //         $waitMinutes = max(1, (int) ceil(now()->diffInMinutes($waitUntil)));
        //     }
        //     
        //     return response()->json([
        //         'message' => "Too many OTP requests. You can request a new code in {$waitMinutes} minute(s). Maximum 3 requests per hour.",
        //     ], 429);
        // }

        // Create or get user
        $user = User::firstOrCreate(
            ['phone' => $phone],
            [
                'name' => 'User_' . substr($phone, -4),
                'email' => 'user_' . $phone . '@example.com',
                'password' => Hash::make(Str::random(16)),
            ]
        );

        // Generate OTP code
        $code = OtpCode::generate($phone, 6, 5);

        // For testing: Accept phone +1111111111 with OTP 123456
        if ($phone === '1111111111') {
            $code = '123456';
            OtpCode::create([
                'phone' => $phone,
                'code' => $code,
                'expires_at' => now()->addMinutes(5),
            ]);
        } else {
            // Send OTP via SMS
            $msg = "Your GekyChat OTP code is: {$code}. Valid for 5 minutes.";
            $resp = $this->sms->sendSms($phone, $msg);

            $ok = is_array($resp) ? ($resp['success'] ?? false) : (bool)$resp;
            if (!$ok) {
                return response()->json(['message' => 'Failed to send OTP'], 500);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'expires_in' => 300, // 5 minutes in seconds
        ]);
    }

    /**
     * Verify OTP
     * POST /api/v1/auth/verify
     */
    public function verifyOtp(Request $r)
    {
        $r->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code'  => ['required', 'string', 'size:6'],
        ]);

        $phone = preg_replace('/\D+/', '', $r->phone);

        // Verify OTP
        if (!OtpCode::verify($phone, $r->code)) {
            return response()->json([
                'message' => 'Invalid OTP code',
            ], 401);
        }

        // Get user
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        // Mark phone as verified
        if (!$user->phone_verified_at) {
            $user->markPhoneAsVerified();
        }

        // Generate token (30 days expiration)
        $token = $user->createToken('mobile', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
                'created_at' => $user->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get current user
     * GET /api/v1/me
     */
    public function me(Request $r)
    {
        return response()->json($r->user());
    }

    /**
     * Logout
     * POST /api/v1/auth/logout
     */
    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()?->delete();
        return response()->json(['success' => true]);
    }
}
