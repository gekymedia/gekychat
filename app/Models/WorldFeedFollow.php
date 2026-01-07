<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorldFeedFollow extends Model
{
    protected $fillable = [
        'follower_id',
        'creator_id',
        'followed_at',
    ];

    protected $casts = [
        'followed_at' => 'datetime',
    ];

    public $timestamps = false;

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
