<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * PHASE 2: Live Broadcast Model
 */
class LiveBroadcast extends Model
{
    protected $fillable = [
        'broadcaster_id',
        'title',
        'status',
        'started_at',
        'ended_at',
        'viewers_count',
        'stream_key',
        'room_name', // LiveKit room name
        'slug', // URL-friendly identifier
        'save_replay',
        'replay_url',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'viewers_count' => 'integer',
        'save_replay' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($broadcast) {
            if (empty($broadcast->stream_key)) {
                $broadcast->stream_key = Str::random(32);
            }
            if (empty($broadcast->room_name)) {
                $broadcast->room_name = 'live_' . $broadcast->broadcaster_id . '_' . time();
            }
            if (empty($broadcast->slug)) {
                $broadcast->slug = $broadcast->generateSlug();
            }
        });
    }
    
    /**
     * Generate unique slug for broadcast
     */
    public function generateSlug(): string
    {
        $randomSuffix = Str::lower(Str::random(8));
        $slug = "broadcast-{$randomSuffix}";
        
        $counter = 1;
        while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $slug = "broadcast-{$randomSuffix}-{$counter}";
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Get the route key name for model binding
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
    
    /**
     * Find broadcast by slug or ID (for backward compatibility)
     */
    public static function findByIdentifier($identifier)
    {
        // Try slug first
        $broadcast = static::where('slug', $identifier)->first();
        if ($broadcast) {
            return $broadcast;
        }
        // Fallback to ID for backward compatibility
        return static::find($identifier);
    }

    public function broadcaster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'broadcaster_id');
    }

    public function viewers(): HasMany
    {
        return $this->hasMany(LiveBroadcastViewer::class, 'broadcast_id');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(LiveBroadcastChat::class, 'broadcast_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'live';
    }
}
