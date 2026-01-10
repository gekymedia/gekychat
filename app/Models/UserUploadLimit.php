<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User Upload Limits Model
 * 
 * Stores per-user overrides for upload limits.
 * These override global settings for specific users (testing, trusted users, etc.)
 */
class UserUploadLimit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'world_feed_max_duration',
        'chat_video_max_size',
        'status_max_duration',
        'notes',
        'set_by_admin_id',
    ];

    protected $casts = [
        'world_feed_max_duration' => 'integer',
        'chat_video_max_size' => 'integer',
        'status_max_duration' => 'integer',
    ];

    /**
     * Get the user this limit applies to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who set this override
     */
    public function setByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_admin_id');
    }

    /**
     * Get user override for a specific limit type
     */
    public static function getUserLimit(int $userId, string $limitType)
    {
        $override = static::where('user_id', $userId)->first();
        
        if (!$override) {
            return null;
        }

        return match($limitType) {
            'world_feed_max_duration' => $override->world_feed_max_duration,
            'chat_video_max_size' => $override->chat_video_max_size,
            'status_max_duration' => $override->status_max_duration,
            default => null,
        };
    }

    /**
     * Get or create override for a user
     */
    public static function getOrCreateForUser(int $userId): self
    {
        return static::firstOrCreate(['user_id' => $userId]);
    }
}
