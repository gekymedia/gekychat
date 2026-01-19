<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorldFeedComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'post_id',
        'user_id',
        'comment',
        'parent_id',
        'likes_count',
    ];

    protected $casts = [
        'likes_count' => 'integer',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(WorldFeedPost::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WorldFeedComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(WorldFeedComment::class, 'parent_id');
    }

    /**
     * Comment likes
     */
    public function likes(): HasMany
    {
        return $this->hasMany(WorldFeedCommentLike::class, 'comment_id');
    }

    /**
     * Check if user liked this comment
     */
    public function isLikedBy(int $userId): bool
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }
}
