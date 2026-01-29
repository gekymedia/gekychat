<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\BotContact;
use App\Models\AuditLog;
use App\Services\ArkeselSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            'app_signature' => ['nullable', 'string', 'max:20'], // Android SMS Retriever hash
        ]);

        $phone = preg_replace('/\D+/', '', $r->phone);
        $appSignature = $r->input('app_signature');

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
            // Format SMS message for auto-detection (Android & iOS)
            // Android SMS Retriever API format: <#> Your code is: 123456 <hash>
            // iOS auto-detection format: Your code is 123456
            if ($appSignature && strlen($appSignature) === 11) {
                // Android format with hash for SMS Retriever API
                $msg = "<#> Your GekyChat code is: {$code} {$appSignature}";
            } else {
                // iOS-friendly format (also works for Android without hash)
                // Format: "Your code is 123456" - works for iOS auto-detection
                $msg = "Your GekyChat code is {$code}. Valid for 5 minutes.";
            }
            
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
                // Record failed attempt for bot user if exists
                if ($botUser = User::where('phone', $phone)->first()) {
                    $botUser->recordFailedLogin();
                    
                    if ($botUser->isLocked()) {
                        return response()->json([
                            'message' => 'Account locked due to multiple failed attempts. Try again in 30 minutes.',
                            'locked_until' => $botUser->locked_until,
                        ], 423);
                    }
                }
                
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
            // Get user first to check lock status
            $user = User::where('phone', $phone)->first();
            
            if (!$user) {
                return response()->json([
                    'message' => 'User not found',
                ], 404);
            }
            
            // Check if account is locked
            if ($user->isLocked()) {
                return response()->json([
                    'message' => 'Account locked due to multiple failed attempts. Try again in 30 minutes.',
                    'locked_until' => $user->locked_until,
                ], 423);
            }
            
            // Normal user - verify OTP
            if (!OtpCode::verify($phone, $r->code)) {
                // Record failed attempt
                $user->recordFailedLogin();
                
                if ($user->isLocked()) {
                    return response()->json([
                        'message' => 'Invalid OTP code. Account locked due to multiple failed attempts. Try again in 30 minutes.',
                        'locked_until' => $user->locked_until,
                    ], 423);
                }
                
                return response()->json([
                    'message' => 'Invalid OTP code',
                ], 401);
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
        
        // NEW: Record successful login with activity tracking
        $user->recordLogin(
            $r->ip(),
            $r->userAgent(),
            $this->getCountryFromIp($r->ip())
        );
        
        // NEW: Log the login event
        AuditLog::log('login', $user, 'User logged in successfully');

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
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'total_logins' => $user->total_logins ?? 1,
            ],
        ]);
    }

    /**
     * Generate QR code for login (for logged-in users to share with other devices)
     * GET /api/v1/auth/qr-code
     */
    public function generateQrCode(Request $r)
    {
        $user = $r->user();
        
        // Generate a temporary token that expires in 5 minutes
        // Use 'qr-login' as both name and ability for easier lookup
        $tempToken = $user->createToken('qr-login', ['qr-login'], now()->addMinutes(5))->plainTextToken;
        
        // Extract just the token part (without ID)
        $tokenParts = explode('|', $tempToken);
        $tokenOnly = $tokenParts[1] ?? $tempToken;
        
        // Create QR code URL
        $qrUrl = "gekychat://login?token=$tokenOnly";
        
        return response()->json([
            'qr_token' => $tokenOnly,
            'qr_url' => $qrUrl,
            'expires_in' => 300, // 5 minutes
        ]);
    }

    /**
     * Login with QR code token
     * POST /api/v1/auth/qr-login
     */
    public function qrLogin(Request $r)
    {
        $r->validate([
            'qr_token' => ['required', 'string'],
        ]);

        $qrToken = $r->input('qr_token');
        
        // Find the token in the database
        // Sanctum stores tokens as: id|hashed_token
        // The token stored in DB is already hashed with SHA-256
        $hashedToken = hash('sha256', $qrToken);
        
        // Find token by hashed value and name (for security)
        $foundToken = PersonalAccessToken::where('token', $hashedToken)
            ->where('name', 'qr-login')
            ->first();
        
        if (!$foundToken) {
            return response()->json([
                'message' => 'Invalid or expired QR code',
            ], 401);
        }
        
        $user = $foundToken->tokenable;
        
        if (!$user) {
            return response()->json([
                'message' => 'Invalid QR code: User not found',
            ], 401);
        }
        
        // Check if token is expired
        if ($foundToken->expires_at && $foundToken->expires_at->isPast()) {
            $foundToken->delete();
            return response()->json([
                'message' => 'QR code has expired. Please generate a new one.',
            ], 401);
        }
        
        // Check if token has 'qr-login' ability (security check)
        $abilities = $foundToken->abilities ?? [];
        if (!in_array('qr-login', $abilities) && !in_array('*', $abilities)) {
            return response()->json([
                'message' => 'Invalid QR code token',
            ], 401);
        }
        
        // Delete the temporary QR token
        $foundToken->delete();
        
        // PHASE 2: Get device info for multi-account support
        $deviceId = $r->input('device_id', 'default');
        $deviceType = $r->input('device_type', 'mobile');
        $accountLabel = $r->input('account_label');
        
        // Generate new token for the scanning device (30 days expiration)
        $newToken = $user->createToken('mobile', ['*'], now()->addDays(30))->plainTextToken;
        
        // PHASE 2: Link token to device
        $accessToken = $user->tokens()->where('token', hash('sha256', explode('|', $newToken)[1] ?? ''))->first();
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
        
        // PHASE 2: Activate this account
        $deviceAccount->activate();
        
        return response()->json([
            'token' => $newToken,
            'device_id' => $deviceId,
            'account_id' => $deviceAccount->id,
            'user' => [
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
        $user = $r->user();
        
        // Delete current token
        $user->currentAccessToken()?->delete();
        
        // Delete all other tokens for this user (full logout)
        $user->tokens()->delete();
        
        // Also delete all web sessions for this user
        \App\Models\UserSession::where('user_id', $user->id)->delete();
        
        // Delete from sessions table if using database sessions
        if (config('session.driver') === 'database') {
            \Illuminate\Support\Facades\DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();
        }
        
        return response()->json(['success' => true, 'message' => 'Logged out from all devices']);
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
            'account_id' => ['required', 'integer'],
            'device_id' => ['required', 'string'],
            'device_type' => ['required', 'in:mobile,desktop,web'],
        ]);

        // Multi-account is a core feature, always available
        // Removed feature flag check to ensure it works for all users

        // Validate account_id exists (but allow for edge cases)
        $deviceAccount = \App\Models\DeviceAccount::where('id', $r->input('account_id'))
            ->where('device_id', $r->input('device_id'))
            ->where('device_type', $r->input('device_type'))
            ->first();

        if (!$deviceAccount) {
            return response()->json([
                'error' => 'Account not found. Please ensure the account is properly registered on this device.',
                'account_id' => $r->input('account_id'),
                'device_id' => $r->input('device_id'),
                'device_type' => $r->input('device_type'),
            ], 404);
        }

        // Activate this account
        $deviceAccount->activate();

        // For web, use session-based authentication instead of tokens
        if ($r->input('device_type') === 'web') {
            // Log in the user via session
            \Illuminate\Support\Facades\Auth::login($deviceAccount->user);
            
            // Update or create web session record
            $sessionId = session()->getId();
            \App\Models\UserSession::updateOrCreate(
                [
                    'user_id' => $deviceAccount->user->id,
                    'session_id' => $sessionId,
                ],
                [
                    'device_type' => $r->input('device_id'),
                    'platform' => $r->header('User-Agent', 'Unknown'),
                    'browser' => $this->detectBrowser($r->header('User-Agent', '')),
                    'last_activity' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Account switched successfully',
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

        // For mobile/desktop, use token-based authentication
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
     * Helper method to detect browser from User-Agent string
     */
    private function detectBrowser($userAgent)
    {
        if (stripos($userAgent, 'Chrome') !== false) return 'Chrome';
        if (stripos($userAgent, 'Firefox') !== false) return 'Firefox';
        if (stripos($userAgent, 'Safari') !== false) return 'Safari';
        if (stripos($userAgent, 'Edge') !== false) return 'Edge';
        if (stripos($userAgent, 'Opera') !== false) return 'Opera';
        return 'Unknown';
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
            ->first();

        if (!$deviceAccount) {
            return response()->json([
                'error' => 'Account not found on this device.',
                'account_id' => $accountId,
                'device_id' => $deviceId,
                'device_type' => $deviceType,
            ], 404);
        }

        // Delete all tokens for this account on this device
        $deviceAccount->user->tokens()
            ->where('device_id', $deviceId)
            ->where('device_type', $deviceType)
            ->delete();

        // Delete device account record
        $deviceAccount->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Authenticate a web QR login session from mobile app
     * POST /api/v1/auth/qr-authenticate
     * Requires: authenticated mobile user + session_token from QR code
     */
    public function authenticateQrSession(Request $r)
    {
        $r->validate([
            'session_token' => ['required', 'string', 'size:32'],
        ]);

        $user = $r->user(); // Authenticated mobile user
        $sessionToken = $r->input('session_token');
        
        $sessionKey = "qr_login_session:{$sessionToken}";
        $session = Cache::get($sessionKey);
        
        if (!$session) {
            return response()->json([
                'message' => 'QR code session not found or expired',
            ], 404);
        }
        
        if ($session['status'] !== 'pending') {
            return response()->json([
                'message' => 'QR code session already processed',
            ], 400);
        }
        
        // Update session to authenticated
        Cache::put($sessionKey, [
            'status' => 'authenticated',
            'user_id' => $user->id,
            'authenticated_at' => now(),
            'expires_at' => $session['expires_at'],
        ], now()->diffInSeconds($session['expires_at']));
        
        return response()->json([
            'success' => true,
            'message' => 'QR code authenticated successfully',
        ]);
    }
    
    /**
     * Get country code from IP address (basic implementation)
     * For production, consider using a service like ipinfo.io or maxmind
     */
    private function getCountryFromIp(?string $ip): ?string
    {
        if (!$ip || $ip === '127.0.0.1' || $ip === '::1') {
            return null;
        }
        
        // For now, return null. In production, use a geolocation service:
        // - ipinfo.io API
        // - MaxMind GeoIP2
        // - CloudFlare headers (CF-IPCountry)
        
        return null;
    }
}
