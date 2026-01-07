<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PHASE 2: Call Participant Model
 * 
 * Tracks participants in group calls and meetings.
 */
class CallParticipant extends Model
{
    protected $fillable = [
        'call_session_id',
        'user_id',
        'status',
        'joined_at',
        'left_at',
        'is_host',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'is_host' => 'boolean',
    ];

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}


