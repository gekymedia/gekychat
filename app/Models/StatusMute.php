<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusMute extends Model
{
    protected $fillable = [
        'user_id',
        'muted_user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mutedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'muted_user_id');
    }

    /**
     * Check if a user has muted another user's status
     */
    public static function isMuted(int $userId, int $mutedUserId): bool
    {
        return self::where('user_id', $userId)
            ->where('muted_user_id', $mutedUserId)
            ->exists();
    }
}

