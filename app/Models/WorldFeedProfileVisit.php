<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records when a user opens a World Feed creator profile.
 * Visits after watching a post (source_post_id set) are a strong
 * affinity signal for personalized feed ranking.
 */
class WorldFeedProfileVisit extends Model
{
    protected $fillable = [
        'visitor_id',
        'creator_id',
        'source_post_id',
        'visited_at',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visitor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function sourcePost(): BelongsTo
    {
        return $this->belongsTo(WorldFeedPost::class, 'source_post_id');
    }
}
