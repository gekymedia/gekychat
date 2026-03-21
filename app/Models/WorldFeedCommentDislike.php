<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorldFeedCommentDislike extends Model
{
    protected $table = 'world_comment_dislikes';

    protected $fillable = [
        'comment_id',
        'user_id',
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(WorldFeedComment::class, 'comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
