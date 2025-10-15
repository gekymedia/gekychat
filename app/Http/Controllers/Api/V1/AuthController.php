<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ArkeselSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private ArkeselSmsService $sms) {}

    public function requestOtp(Request $r)
    {
        $r->validate(['phone' => ['required','regex:/^0\d{9}$/']]);
        $phone = preg_replace('/\D+/', '', $r->phone);

        $user = User::firstOrCreate(
            ['phone' => $phone],
            [
                'name' => 'User_' . substr($phone, -4),
                'email' => 'user_' . $phone . '@example.com',
                'password' => Hash::make(Str::random(16)),
            ]
        );

        $code = random_int(100000, 999999);
        $user->forceFill([
            'otp_code' => $code,
            'otp_expires_at' => now()->addMinutes(5),
        ])->save();

        $msg = "Your OTP code is: {$code}. Valid for 5 minutes.";
        $resp = $this->sms->sendSms($phone, $msg);

        $ok = is_array($resp) ? ($resp['success'] ?? false) : (bool)$resp;
        if (!$ok) {
            return response()->json(['message' => 'Failed to send OTP'], 500);
        }

        return response()->json(['ok' => true]);
    }

    public function verifyOtp(Request $r)
    {
        $r->validate([
            'phone' => ['required','regex:/^0\d{9}$/'],
            'code'  => ['required','digits:6'],
        ]);
        $phone = preg_replace('/\D+/', '', $r->phone);
        $user = User::where('phone', $phone)->first();

        if (!$user || !$user->otp_code || $user->otp_code !== $r->code || now()->gt($user->otp_expires_at)) {
            return response()->json(['message' => 'Invalid or expired code'], 422);
        }

        $user->forceFill([
            'otp_code' => null,
            'otp_expires_at' => null,
            'phone_verified_at' => now(),
        ])->save();

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
            ],
        ]);
    }

    public function me(Request $r)    { return response()->json($r->user()); }
    public function logout(Request $r) { $r->user()->currentAccessToken()?->delete(); return response()->json(['ok'=>true]); }
}
