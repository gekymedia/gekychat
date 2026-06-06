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
        'device_type',
        'platform',
        'device_id',
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
     * Register or update a device token (same user + device_id = one row; reinstall updates last seen).
     */
    public static function register(int $userId, string $token, string $deviceType, ?string $deviceId = null): self
    {
        $attributes = [
            'token' => $token,
        ];

        if (Schema::hasColumn('device_tokens', 'device_type')) {
            $attributes['device_type'] = $deviceType;
        } elseif (Schema::hasColumn('device_tokens', 'platform')) {
            $attributes['platform'] = $deviceType;
        }

        if (Schema::hasColumn('device_tokens', 'last_used_at')) {
            $attributes['last_used_at'] = now();
        }
        if (Schema::hasColumn('device_tokens', 'is_active')) {
            $attributes['is_active'] = true;
        }

        return self::updateOrCreate(
            [
                'user_id' => $userId,
                'device_id' => $deviceId,
            ],
            $attributes
        );
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

        return $query->pluck('token')->filter()->unique()->values()->all();
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

