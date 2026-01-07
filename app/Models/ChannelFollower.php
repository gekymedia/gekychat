<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PHASE 2: Channel Follower Model
 * 
 * Tracks users who follow channels (one-way subscription).
 */
class ChannelFollower extends Model
{
    protected $fillable = [
        'channel_id',
        'user_id',
        'followed_at',
        'muted_until',
    ];

    protected $casts = [
        'followed_at' => 'datetime',
        'muted_until' => 'datetime',
    ];

    public $timestamps = false;

    /**
     * Channel (Group with type='channel')
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'channel_id');
    }

    /**
     * User who follows
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
