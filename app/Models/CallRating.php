<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallRating extends Model
{
    protected $fillable = [
        'call_session_id',
        'user_id',
        'rating',
        'issues',
        'comment',
        'call_type',
        'duration_seconds',
        'client_meta',
    ];

    protected $casts = [
        'issues' => 'array',
        'client_meta' => 'array',
    ];

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class, 'call_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
