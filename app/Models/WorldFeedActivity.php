<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Instagram/TikTok-style activity feed for World Feed and Live.
 * Types: post_like, post_comment, comment_reply, new_follower, live_started.
 */
class WorldFeedActivity extends Model
{
    protected $fillable = [
        'user_id',
        'actor_id',
        'type',
        'post_id',
        'comment_id',
        'broadcast_id',
        'summary',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(WorldFeedPost::class, 'post_id');
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(WorldFeedComment::class, 'comment_id');
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(LiveBroadcast::class, 'broadcast_id');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public static function types(): array
    {
        return [
            'post_like' => 'post_like',
            'post_comment' => 'post_comment',
            'comment_reply' => 'comment_reply',
            'new_follower' => 'new_follower',
            'live_started' => 'live_started',
        ];
    }
}
