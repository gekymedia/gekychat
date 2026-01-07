<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a voice or video call between two users or within a group.
 * PHASE 2: Extended for group calls and meetings.
 */
class CallSession extends Model
{
    protected $fillable = [
        'caller_id',
        'callee_id',
        'group_id',
        'type',
        'status',
        'is_meeting', // PHASE 2: Meeting-style call (join/leave without ending)
        'invite_token', // PHASE 2: Token for invite links
        'host_id', // PHASE 2: Meeting host
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'is_meeting' => 'boolean',
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function callee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'callee_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * PHASE 2: Meeting host
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /**
     * PHASE 2: Call participants
     */
    public function participants(): HasMany
    {
        return $this->hasMany(CallParticipant::class);
    }

    /**
     * PHASE 2: Get active participants (joined and not left)
     */
    public function activeParticipants(): HasMany
    {
        return $this->participants()
            ->where('status', 'joined')
            ->whereNull('left_at');
    }

    /**
     * PHASE 2: Check if user is a participant
     */
    public function hasParticipant(int $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }
}