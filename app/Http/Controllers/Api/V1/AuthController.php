<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\BotContact;
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

        // Check if this is a bot number
        $bot = BotContact::getByPhone($phone);
        if ($bot) {
            // Bot number - don't send SMS, just return success
            // The code is stored in the bot_contacts table and will be verified in verifyOtp
            return response()->json([
                'success' => true,
                'message' => 'Please enter the 6-digit bot code',
                'is_bot' => true,
                'expires_in' => 0, // Bot codes don't expire (or can be set to a long time)
            ]);
        }

        // PHASE 0: Rate limiting temporarily disabled for testing
        // TODO (PHASE 0): Re-enable rate limiting after testing phase
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

        // Check if this is a bot number
        $bot = BotContact::getByPhone($phone);
        if ($bot) {
            // Verify bot code
            if (!$bot->verifyCode($r->code)) {
                return response()->json([
                    'message' => 'Invalid bot code',
                ], 401);
            }

            // Get or create user for bot
            $user = $bot->getOrCreateUser();

            // Mark phone as verified (bots don't need SMS verification)
            if (!$user->phone_verified_at) {
                $user->markPhoneAsVerified();
            }
        } else {
            // Normal user - verify OTP
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
        }

        // PHASE 2: Get device info for multi-account support
        $deviceId = $r->input('device_id', 'default');
        $deviceType = $r->input('device_type', 'mobile'); // 'mobile' or 'desktop'
        $accountLabel = $r->input('account_label'); // Optional label

        // Generate token (30 days expiration)
        $token = $user->createToken('mobile', ['*'], now()->addDays(30))->plainTextToken;
        
        // PHASE 2: Link token to device
        $accessToken = $user->tokens()->where('token', hash('sha256', explode('|', $token)[1] ?? ''))->first();
        if ($accessToken) {
            $accessToken->update([
                'device_id' => $deviceId,
                'device_type' => $deviceType,
                'account_label' => $accountLabel,
            ]);
        }

        // PHASE 2: Create or update device account record
        $deviceAccount = \App\Models\DeviceAccount::updateOrCreate(
            [
                'device_id' => $deviceId,
                'device_type' => $deviceType,
                'user_id' => $user->id,
            ],
            [
                'account_label' => $accountLabel,
                'last_used_at' => now(),
            ]
        );

        // PHASE 2: Activate this account (deactivate others on same device)
        $deviceAccount->activate();

        return response()->json([
            'token' => $token,
            'device_id' => $deviceId,
            'account_id' => $deviceAccount->id,
            'user'  => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'username' => $user->username,
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

    /**
     * PHASE 2: Get all accounts on this device
     * GET /api/v1/auth/accounts
     */
    public function getAccounts(Request $r)
    {
        $deviceId = $r->input('device_id', 'default');
        $deviceType = $r->input('device_type', 'mobile');

        // Multi-account is a core feature, always available
        // Removed feature flag check to ensure it works for all users

        $accounts = \App\Models\DeviceAccount::where('device_id', $deviceId)
            ->where('device_type', $deviceType)
            ->with('user:id,name,phone,username,avatar_path')
            ->orderBy('last_used_at', 'desc')
            ->get();

        return response()->json([
            'data' => $accounts->map(function ($account) {
                return [
                    'id' => $account->id,
                    'user_id' => $account->user_id,
                    'account_label' => $account->account_label,
                    'is_active' => $account->is_active,
                    'last_used_at' => $account->last_used_at?->toIso8601String(),
                    'user' => [
                        'id' => $account->user->id,
                        'name' => $account->user->name,
                        'phone' => $account->user->phone,
                        'username' => $account->user->username,
                        'avatar_url' => $account->user->avatar_url,
                    ],
                ];
            }),
        ]);
    }

    /**
     * PHASE 2: Switch to a different account on this device
     * POST /api/v1/auth/switch-account
     */
    public function switchAccount(Request $r)
    {
        $r->validate([
            'account_id' => ['required', 'exists:device_accounts,id'],
            'device_id' => ['required', 'string'],
            'device_type' => ['required', 'in:mobile,desktop'],
        ]);

        // Multi-account is a core feature, always available
        // Removed feature flag check to ensure it works for all users

        $deviceAccount = \App\Models\DeviceAccount::where('id', $r->input('account_id'))
            ->where('device_id', $r->input('device_id'))
            ->where('device_type', $r->input('device_type'))
            ->firstOrFail();

        // Activate this account
        $deviceAccount->activate();

        // Get or create token for this account
        $token = $deviceAccount->user->tokens()
            ->where('device_id', $r->input('device_id'))
            ->where('device_type', $r->input('device_type'))
            ->where('name', 'mobile')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$token || $token->expires_at && $token->expires_at->isPast()) {
            // Create new token if none exists or expired
            $newToken = $deviceAccount->user->createToken('mobile', ['*'], now()->addDays(30))->plainTextToken;
            $tokenParts = explode('|', $newToken);
            $tokenPlain = $tokenParts[1] ?? '';
            
            $accessToken = $deviceAccount->user->tokens()
                ->where('token', hash('sha256', $tokenPlain))
                ->first();
                
            if ($accessToken) {
                $accessToken->update([
                    'device_id' => $r->input('device_id'),
                    'device_type' => $r->input('device_type'),
                    'account_label' => $deviceAccount->account_label,
                ]);
            }
            $tokenValue = $newToken;
        } else {
            // Return existing token
            $tokenValue = $token->id . '|' . $token->token;
        }

        return response()->json([
            'token' => $tokenValue,
            'account_id' => $deviceAccount->id,
            'user' => [
                'id' => $deviceAccount->user->id,
                'name' => $deviceAccount->user->name,
                'phone' => $deviceAccount->user->phone,
                'username' => $deviceAccount->user->username,
                'avatar_url' => $deviceAccount->user->avatar_url,
            ],
        ]);
    }

    /**
     * PHASE 2: Remove an account from this device
     * DELETE /api/v1/auth/accounts/{accountId}
     */
    public function removeAccount(Request $r, $accountId)
    {
        $deviceId = $r->input('device_id', 'default');
        $deviceType = $r->input('device_type', 'mobile');

        // Multi-account is a core feature, always available
        // Removed feature flag check to ensure it works for all users

        $deviceAccount = \App\Models\DeviceAccount::where('id', $accountId)
            ->where('device_id', $deviceId)
            ->where('device_type', $deviceType)
            ->firstOrFail();

        // Delete all tokens for this account on this device
        $deviceAccount->user->tokens()
            ->where('device_id', $deviceId)
            ->where('device_type', $deviceType)
            ->delete();

        // Delete device account record
        $deviceAccount->delete();

        return response()->json(['success' => true]);
    }
}
