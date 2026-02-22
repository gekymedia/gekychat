<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorldFeedReport extends Model
{
    protected $fillable = [
        'post_id',
        'reporter_id',
        'reason',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(WorldFeedPost::class, 'post_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
