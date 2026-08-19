<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class DeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'voip_token',
        'device_type',
        'platform',
        'device_id',
        'installation_id',
        'is_active',
        'last_used_at',
        'app_version',
        'device_model',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getResolvedDeviceTypeAttribute(): string
    {
        return $this->device_type ?? $this->platform ?? 'unknown';
    }

    /**
     * Register or refresh push token. Upserts by installation_id when provided
     * (stable across iOS reinstalls); falls back to user_id + device_id for legacy clients.
     */
    public static function register(
        int $userId,
        string $token,
        string $deviceType,
        ?string $deviceId = null,
        ?string $voipToken = null,
        ?string $installationId = null,
    ): self {
        $attributes = [
            'token' => $token,
        ];

        if ($voipToken !== null && $voipToken !== '' && Schema::hasColumn('device_tokens', 'voip_token')) {
            $attributes['voip_token'] = $voipToken;
        }

        if (Schema::hasColumn('device_tokens', 'device_type')) {
            $attributes['device_type'] = $deviceType;
        } elseif (Schema::hasColumn('device_tokens', 'platform')) {
            $attributes['platform'] = $deviceType;
        }

        if ($deviceId !== null && $deviceId !== '' && Schema::hasColumn('device_tokens', 'device_id')) {
            $attributes['device_id'] = $deviceId;
        }

        if ($installationId !== null && $installationId !== '' && Schema::hasColumn('device_tokens', 'installation_id')) {
            $attributes['installation_id'] = $installationId;
        }

        if (Schema::hasColumn('device_tokens', 'last_used_at')) {
            $attributes['last_used_at'] = now();
        }
        if (Schema::hasColumn('device_tokens', 'is_active')) {
            $attributes['is_active'] = true;
        }

        $row = self::resolveRegisterRow($userId, $deviceId, $installationId, $attributes);

        self::dedupeAfterRegister($userId, $deviceType, $row, $deviceId, $token);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected static function resolveRegisterRow(
        int $userId,
        ?string $deviceId,
        ?string $installationId,
        array $attributes,
    ): self {
        $hasInstallationColumn = Schema::hasColumn('device_tokens', 'installation_id');

        if ($installationId !== null && $installationId !== '' && $hasInstallationColumn) {
            $existing = self::where('user_id', $userId)
                ->where('installation_id', $installationId)
                ->first();

            if (! $existing && $deviceId !== null && $deviceId !== '') {
                $legacy = self::where('user_id', $userId)
                    ->whereNull('installation_id')
                    ->where('device_id', $deviceId)
                    ->orderByDesc('last_used_at')
                    ->orderByDesc('updated_at')
                    ->first();

                if ($legacy) {
                    $legacy->installation_id = $installationId;
                    $legacy->fill($attributes);
                    $legacy->save();

                    return $legacy;
                }
            }

            return self::updateOrCreate(
                [
                    'user_id' => $userId,
                    'installation_id' => $installationId,
                ],
                $attributes,
            );
        }

        return self::updateOrCreate(
            [
                'user_id' => $userId,
                'device_id' => $deviceId,
            ],
            $attributes,
        );
    }

    /**
     * Retire duplicate rows after a successful registration.
     */
    protected static function dedupeAfterRegister(
        int $userId,
        string $deviceType,
        self $row,
        ?string $hardwareDeviceId,
        string $token,
    ): void {
        if (! Schema::hasColumn('device_tokens', 'is_active')) {
            return;
        }

        // Same FCM token must not exist on multiple rows.
        self::where('user_id', $userId)
            ->where('id', '!=', $row->id)
            ->where('token', $token)
            ->update(['is_active' => false]);

        // Legacy rows (pre-installation_id clients) for this hardware.
        if ($hardwareDeviceId !== null && $hardwareDeviceId !== ''
            && Schema::hasColumn('device_tokens', 'installation_id')) {
            self::where('user_id', $userId)
                ->where('id', '!=', $row->id)
                ->whereNull('installation_id')
                ->where('device_id', $hardwareDeviceId)
                ->update(['is_active' => false]);
        }

        // App upgraded to installation_id: retire all other legacy rows on this platform.
        // A second physical device must update the app to register its own installation_id.
        if ($row->installation_id && Schema::hasColumn('device_tokens', 'installation_id')) {
            $legacy = self::where('user_id', $userId)
                ->where('id', '!=', $row->id)
                ->whereNull('installation_id');

            if (Schema::hasColumn('device_tokens', 'device_type')) {
                $legacy->where('device_type', $deviceType);
            } elseif (Schema::hasColumn('device_tokens', 'platform')) {
                $legacy->where('platform', $deviceType);
            }

            $legacy->update(['is_active' => false]);
        }

        // iOS: one phone should not keep multiple active FCM rows (reinstall / legacy
        // registration bugs caused 3–4 identical APNs banners per message).
        if (strtolower($deviceType) === 'ios' && Schema::hasColumn('device_tokens', 'is_active')) {
            self::where('user_id', $userId)
                ->where('id', '!=', $row->id)
                ->where('is_active', true)
                ->when(
                    Schema::hasColumn('device_tokens', 'device_type'),
                    fn ($q) => $q->where('device_type', 'ios'),
                    fn ($q) => $q->where('platform', 'ios'),
                )
                ->update(['is_active' => false]);
        }

        self::pruneStaleTokensForUser($userId, $deviceType, $row->id);
    }

    /**
     * Deactivate tokens unused for 30+ days (reinstall ghosts, abandoned devices).
     */
    public static function pruneStaleTokensForUser(
        int $userId,
        string $deviceType,
        ?int $keepRowId = null,
    ): void {
        if (! Schema::hasColumn('device_tokens', 'is_active')) {
            return;
        }

        $base = self::where('user_id', $userId);
        if (Schema::hasColumn('device_tokens', 'device_type')) {
            $base->where('device_type', $deviceType);
        } elseif (Schema::hasColumn('device_tokens', 'platform')) {
            $base->where('platform', $deviceType);
        }

        (clone $base)
            ->where('is_active', true)
            ->when($keepRowId !== null, fn ($q) => $q->where('id', '!=', $keepRowId))
            ->where(function ($q) {
                $q->whereNull('last_used_at')
                    ->orWhere('last_used_at', '<', now()->subDays(30));
            })
            ->update(['is_active' => false]);
    }

    /**
     * @deprecated Use pruneStaleTokensForUser()
     */
    public static function pruneDuplicateTokensForUser(
        int $userId,
        string $deviceType,
        ?int $keepRowId = null,
    ): void {
        self::pruneStaleTokensForUser($userId, $deviceType, $keepRowId);
    }

    /**
     * Update PushKit VoIP token for an existing installation / device row.
     */
    public static function registerVoipToken(
        int $userId,
        string $voipToken,
        ?string $deviceId = null,
        ?string $installationId = null,
    ): self {
        $attributes = [];
        if (Schema::hasColumn('device_tokens', 'voip_token')) {
            $attributes['voip_token'] = $voipToken;
        }
        if (Schema::hasColumn('device_tokens', 'device_type')) {
            $attributes['device_type'] = 'ios';
        } elseif (Schema::hasColumn('device_tokens', 'platform')) {
            $attributes['platform'] = 'ios';
        }
        if (Schema::hasColumn('device_tokens', 'last_used_at')) {
            $attributes['last_used_at'] = now();
        }
        if (Schema::hasColumn('device_tokens', 'is_active')) {
            $attributes['is_active'] = true;
        }
        if ($deviceId !== null && $deviceId !== '' && Schema::hasColumn('device_tokens', 'device_id')) {
            $attributes['device_id'] = $deviceId;
        }
        if ($installationId !== null && $installationId !== '' && Schema::hasColumn('device_tokens', 'installation_id')) {
            $attributes['installation_id'] = $installationId;
        }

        if ($installationId !== null && $installationId !== '' && Schema::hasColumn('device_tokens', 'installation_id')) {
            $row = self::firstOrNew([
                'user_id' => $userId,
                'installation_id' => $installationId,
            ]);
        } else {
            $row = self::firstOrNew([
                'user_id' => $userId,
                'device_id' => $deviceId,
            ]);
        }

        if (! $row->exists && empty($row->token)) {
            $row->token = 'pending-fcm';
        }

        $row->fill($attributes);
        $row->save();

        self::dedupeAfterRegister($userId, 'ios', $row, $deviceId, (string) $row->token);

        return $row;
    }

    /**
     * Get all tokens for a user
     */
    public static function getTokensForUser(int $userId): array
    {
        $query = self::where('user_id', $userId);

        if (Schema::hasColumn('device_tokens', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->pluck('token')
            ->filter(fn ($t) => $t !== '' && $t !== 'pending-fcm')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Deactivate or remove an invalid token after FCM rejection.
     */
    public static function deactivateToken(string $token): bool
    {
        if (Schema::hasColumn('device_tokens', 'is_active')) {
            return self::where('token', $token)->update(['is_active' => false]) > 0;
        }

        return self::where('token', $token)->delete() > 0;
    }

    /**
     * Remove invalid tokens (legacy alias).
     */
    public static function removeToken(string $token): bool
    {
        return self::deactivateToken($token);
    }
}
