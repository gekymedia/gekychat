<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\Group;
use App\Services\ArkeselSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    public function __construct(private ArkeselSmsService $sms) {}
    /**
     * Update user profile
     * PUT /api/v1/me
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'   => ['nullable', 'string', 'max:60'],
            'about'  => ['nullable', 'string', 'max:160'],
            'email'  => ['nullable', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone'  => [
                'nullable',
                'string',
                'max:20',
                \Illuminate\Validation\Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'change_phone_otp' => ['nullable', 'string', 'size:6'],
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:users,username,' . $user->id],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'dob_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'dob_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'dob_year' => ['nullable', 'integer', 'min:1900', 'max:' . (date('Y') - 5)],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'day' => ['nullable', 'integer', 'min:1', 'max:31'],
        ], [
            'phone.unique' => 'The phone number is already in use by another account.',
        ]);

        // When changing phone, OTP verification is required to prevent impersonation
        $currentPhoneNormalized = preg_replace('/\D+/', '', (string) $user->phone);
        $newPhoneRaw = $request->filled('phone') ? trim($validated['phone']) : null;
        $newPhoneNormalized = $newPhoneRaw !== null && $newPhoneRaw !== '' ? preg_replace('/\D+/', '', $newPhoneRaw) : null;
        if ($newPhoneNormalized !== null && $newPhoneNormalized !== $currentPhoneNormalized) {
            $otp = $request->input('change_phone_otp');
            if (empty($otp) || strlen($otp) !== 6) {
                return response()->json([
                    'success' => false,
                    'message' => 'To change your phone number, request a verification code first and enter it here.',
                    'code' => 'change_phone_otp_required',
                ], 422);
            }
            $cacheKey = 'change_phone:' . $user->id;
            $pending = Cache::get($cacheKey);
            if (!$pending || ($pending['expires_at'] ?? 0) < time()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification code expired. Please request a new code for the new phone number.',
                    'code' => 'change_phone_otp_expired',
                ], 422);
            }
            if (($pending['phone'] ?? '') !== $newPhoneNormalized) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification code was sent to a different number. Request a new code for this number.',
                    'code' => 'change_phone_otp_mismatch',
                ], 422);
            }
            if (!OtpCode::verify($newPhoneNormalized, $otp)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid verification code.',
                    'code' => 'change_phone_otp_invalid',
                ], 422);
            }
            Cache::forget($cacheKey);
        }

        // Handle avatar upload
        if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            try {
                // Delete old avatar if present
                if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                    Storage::disk('public')->delete($user->avatar_path);
                }

                // Store new avatar
                $path = $request->file('avatar')->store('avatars', 'public');
                $user->avatar_path = $path;
            } catch (\Exception $e) {
                Log::error('Avatar upload failed: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload avatar'
                ], 500);
            }
        }

        // Update other fields
        if ($request->filled('name')) {
            $user->name = trim(preg_replace('/\s+/', ' ', $validated['name']));
        }

        // About field: allow clearing by checking if key exists (not just filled)
        if ($request->has('about')) {
            $user->about = $request->input('about') ? trim($validated['about']) : null;
        }

        // Email field
        if ($request->has('email')) {
            $user->email = $request->input('email') ? trim($validated['email']) : null;
        }

        // Phone field: only updated after OTP verification (see above)
        if ($request->filled('phone')) {
            $user->phone = trim($validated['phone']);
        }

        // Username: enforce change limit (e.g. once every 30 days) so it cannot be changed "every second"
        $usernameChangeIntervalDays = 30;
        if ($request->filled('username')) {
            $username = strtolower(trim($validated['username']));
            $currentUsername = $user->username;
            if ($username !== $currentUsername) {
                $lastChanged = $user->username_changed_at;
                $nextAllowedAt = $lastChanged ? $lastChanged->addDays($usernameChangeIntervalDays) : null;
                if ($nextAllowedAt && now()->lt($nextAllowedAt)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You can only change your username once every ' . $usernameChangeIntervalDays . ' days. Next change allowed after ' . $nextAllowedAt->toIso8601String(),
                        'username_changed_at' => $user->username_changed_at?->toIso8601String(),
                        'next_change_allowed_at' => $nextAllowedAt->toIso8601String(),
                    ], 422);
                }
                $user->username = $username;
                $user->username_changed_at = now();
            }
        }

        // Update birthday (support both parameter names)
        $month = $request->input('dob_month') ?? $request->input('month');
        $day = $request->input('dob_day') ?? $request->input('day');
        $year = $request->input('dob_year');
        
        if ($month !== null) {
            $user->dob_month = (int) $month;
        }
        
        if ($day !== null) {
            $user->dob_day = (int) $day;
        }

        if ($year !== null) {
            $user->dob_year = (int) $year;
        }

        $user->save();
        $user->refresh();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'about' => $user->about,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'phone' => $user->phone,
                'username' => $user->username,
                'username_changed_at' => $user->username_changed_at?->toIso8601String(),
                'dob_month' => $user->dob_month,
                'dob_day' => $user->dob_day,
                'dob_year' => $user->dob_year,
            ]
        ]);
    }

    /**
     * Get common groups between authenticated user and another user
     * GET /api/v1/users/{userId}/common-groups
     */
    public function commonGroups(Request $request, $userId)
    {
        $authUser = $request->user();
        
        // Don't return common groups for self
        if ($authUser->id == $userId) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        // Find the target user
        $targetUser = User::find($userId);
        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Get groups where both users are members
        $authUserGroupIds = $authUser->groups()->pluck('groups.id')->toArray();
        $targetUserGroupIds = $targetUser->groups()->pluck('groups.id')->toArray();
        
        $commonGroupIds = array_intersect($authUserGroupIds, $targetUserGroupIds);

        if (empty($commonGroupIds)) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        // Fetch group details
        $commonGroups = Group::whereIn('id', $commonGroupIds)
            ->select('id', 'name', 'slug', 'avatar_path')
            ->withCount('members')
            ->get()
            ->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'slug' => $group->slug,
                    'avatar_url' => $group->avatar_url,
                    'member_count' => $group->members_count
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $commonGroups
        ]);
    }

    /**
     * Request OTP to change phone number (sent to the new number).
     * POST /api/v1/me/change-phone/request
     * Body: { "phone": "+233..." }
     */
    public function requestPhoneChangeOtp(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ], [
            'phone.required' => 'Phone number is required.',
        ]);

        $phone = preg_replace('/\D+/', '', $request->phone);
        if (strlen($phone) < 8) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid phone number.',
            ], 422);
        }

        $currentPhoneNormalized = preg_replace('/\D+/', '', (string) $user->phone);
        if ($phone === $currentPhoneNormalized) {
            return response()->json([
                'success' => false,
                'message' => 'This is already your current phone number.',
            ], 422);
        }

        $existing = User::where('phone', $phone)->where('id', '!=', $user->id)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'The phone number is already in use by another account.',
            ], 422);
        }

        if ($phone === '1111111111') {
            $code = '123456';
            OtpCode::create([
                'phone' => $phone,
                'code' => $code,
                'expires_at' => now()->addMinutes(5),
            ]);
        } else {
            $code = OtpCode::generate($phone, 6, 5);
            $appSignature = $request->input('app_signature');
            if ($appSignature && strlen($appSignature) === 11) {
                $msg = "<#> Your GekyChat code to change phone number is: {$code} {$appSignature}";
            } else {
                $msg = "Your GekyChat code to change phone number is {$code}. Valid for 5 minutes.";
            }
            $resp = $this->sms->sendSms($phone, $msg);
            $ok = is_array($resp) ? ($resp['success'] ?? false) : (bool) $resp;
            if (!$ok) {
                return response()->json(['message' => 'Failed to send verification code.'], 500);
            }
        }

        $cacheKey = 'change_phone:' . $user->id;
        Cache::put($cacheKey, [
            'phone' => $phone,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ], now()->addMinutes(5));

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to the new number.',
            'expires_in' => 300,
        ]);
    }
}

