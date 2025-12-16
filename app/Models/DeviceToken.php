<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'device_type',
        'device_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Register or update a device token
     */
    public static function register(int $userId, string $token, string $deviceType, ?string $deviceId = null): self
    {
        return self::updateOrCreate(
            [
                'user_id' => $userId,
                'device_id' => $deviceId,
            ],
            [
                'token' => $token,
                'device_type' => $deviceType,
            ]
        );
    }

    /**
     * Get all tokens for a user
     */
    public static function getTokensForUser(int $userId): array
    {
        return self::where('user_id', $userId)
            ->pluck('token')
            ->toArray();
    }

    /**
     * Remove invalid tokens
     */
    public static function removeToken(string $token): bool
    {
        return self::where('token', $token)->delete() > 0;
    }
}

