<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PHASE 2: World Feed Post Model
 * 
 * Public discovery feed similar to TikTok - short-form videos and posts.
 */
class WorldFeedPost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'creator_id',
        'type',
        'caption',
        'media_url',
        'thumbnail_url',
        'duration',
        'likes_count',
        'comments_count',
        'views_count',
        'shares_count',
        'is_public',
        'tags',
        'share_code',
    ];

    protected $casts = [
        'duration' => 'integer',
        'likes_count' => 'integer',
        'comments_count' => 'integer',
        'views_count' => 'integer',
        'shares_count' => 'integer',
        'is_public' => 'boolean',
        'tags' => 'array',
    ];

    /**
     * Creator (User who posted)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Likes
     */
    public function likes(): HasMany
    {
        return $this->hasMany(WorldFeedLike::class, 'post_id');
    }

    /**
     * Comments
     */
    public function comments(): HasMany
    {
        return $this->hasMany(WorldFeedComment::class, 'post_id');
    }

    /**
     * Views
     */
    public function views(): HasMany
    {
        return $this->hasMany(WorldFeedView::class, 'post_id');
    }

    /**
     * Check if user liked this post
     */
    public function isLikedBy(int $userId): bool
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }

    /**
     * Mark as viewed by user
     */
    public function markAsViewed(?int $userId = null): void
    {
        if ($userId && $this->views()->where('user_id', $userId)->exists()) {
            return; // Already viewed
        }

        $this->views()->create([
            'user_id' => $userId,
            'viewed_at' => now(),
        ]);
        
        $this->increment('views_count');
    }

    /**
     * Get media URL
     */
    public function getMediaUrlAttribute($value)
    {
        if (!$value) return null;
        
        if (str_starts_with($value, 'http')) {
            return $value;
        }
        
        return \App\Helpers\UrlHelper::secureStorageUrl($value, 'public');
    }

    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrlAttribute($value)
    {
        if (!$value) return null;
        
        if (str_starts_with($value, 'http')) {
            return $value;
        }
        
        return \App\Helpers\UrlHelper::secureStorageUrl($value, 'public');
    }

    /**
     * Generate a unique share code
     */
    public static function generateShareCode(): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $length = 10;
        
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (self::where('share_code', $code)->exists());
        
        return $code;
    }

    /**
     * Boot method to auto-generate share code on creation
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($post) {
            if (empty($post->share_code)) {
                $post->share_code = self::generateShareCode();
            }
        });
    }
}
