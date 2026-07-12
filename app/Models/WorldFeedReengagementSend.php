<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorldFeedReengagementSend extends Model
{
    protected $table = 'world_feed_reengagement_sends';

    protected $fillable = [
        'user_id',
        'post_id',
        'sent_at',
        'send_date',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(WorldFeedPost::class, 'post_id');
    }
}
