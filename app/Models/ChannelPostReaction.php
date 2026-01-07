<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelPostReaction extends Model
{
    protected $fillable = [
        'post_id',
        'user_id',
        'emoji',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(ChannelPost::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
