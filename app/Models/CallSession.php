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
        'last_joined_at', // Last time a participant joined; used for 24h link expiry
    ];

    protected $casts = [
        'is_meeting' => 'boolean',
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
        'last_joined_at' => 'datetime',
    ];

    /**
     * Call link expiry: 1:1 = 1 hour since last join; group = 24 hours since last join.
     */
    public function isLinkExpired(): bool
    {
        $ref = $this->last_joined_at ?? $this->started_at ?? $this->created_at;
        if (!$ref) {
            return false;
        }
        $hours = $this->group_id === null ? 1 : 24;
        return $ref->lt(now()->subHours($hours));
    }

    /**
     * Scope: exclude calls whose link has expired (1h for 1:1, 24h for group since last join).
     */
    public function scopeNotExpired($query)
    {
        $oneHour = now()->subHour();
        $twentyFourHours = now()->subHours(24);
        return $query->whereRaw(
            '((group_id IS NULL AND COALESCE(last_joined_at, started_at, created_at) >= ?) OR (group_id IS NOT NULL AND COALESCE(last_joined_at, started_at, created_at) >= ?))',
            [$oneHour, $twentyFourHours]
        );
    }

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

    /**
     * Post-call quality ratings (WhatsApp-style feedback).
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(CallRating::class, 'call_session_id');
    }
}