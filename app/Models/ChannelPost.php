<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PHASE 2: Channel Post Model
 * 
 * Channels are one-way broadcasts. Only admins can post to channels.
 * Channel posts are separate from group_messages.
 */
class ChannelPost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'channel_id',
        'posted_by',
        'type',
        'body',
        'media_url',
        'thumbnail_url',
        'views_count',
        'reactions_count',
    ];

    protected $casts = [
        'views_count' => 'integer',
        'reactions_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Channel (Group with type='channel')
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'channel_id');
    }

    /**
     * User who posted this
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * Reactions to this post
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(ChannelPostReaction::class, 'post_id');
    }

    /**
     * Views of this post
     */
    public function views(): HasMany
    {
        return $this->hasMany(ChannelPostView::class, 'post_id');
    }

    /**
     * Check if user has viewed this post
     */
    public function isViewedBy(int $userId): bool
    {
        return $this->views()->where('user_id', $userId)->exists();
    }

    /**
     * Mark as viewed by user
     */
    public function markAsViewed(int $userId): void
    {
        if (!$this->isViewedBy($userId)) {
            $this->views()->create([
                'user_id' => $userId,
                'viewed_at' => now(),
            ]);
            $this->increment('views_count');
        }
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
}
