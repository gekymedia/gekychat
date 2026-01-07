<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveBroadcastChat extends Model
{
    protected $table = 'live_broadcast_chat';

    protected $fillable = [
        'broadcast_id',
        'user_id',
        'message',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(LiveBroadcast::class, 'broadcast_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}


