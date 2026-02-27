<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Live Broadcast Gift Model
 * 
 * Tracks Sika coin gifts sent during live broadcasts.
 */
class LiveBroadcastGift extends Model
{
    protected $fillable = [
        'broadcast_id',
        'sender_id',
        'receiver_id',
        'gift_type',
        'coins',
        'message',
    ];

    protected $casts = [
        'coins' => 'integer',
    ];

    /**
     * Available gift types with their coin values and display info
     */
    public const GIFT_TYPES = [
        'rose' => ['coins' => 1, 'emoji' => '🌹', 'label' => 'Rose'],
        'heart' => ['coins' => 5, 'emoji' => '❤️', 'label' => 'Heart'],
        'star' => ['coins' => 10, 'emoji' => '⭐', 'label' => 'Star'],
        'fire' => ['coins' => 25, 'emoji' => '🔥', 'label' => 'Fire'],
        'diamond' => ['coins' => 50, 'emoji' => '💎', 'label' => 'Diamond'],
        'crown' => ['coins' => 100, 'emoji' => '👑', 'label' => 'Crown'],
        'rocket' => ['coins' => 500, 'emoji' => '🚀', 'label' => 'Rocket'],
        'galaxy' => ['coins' => 1000, 'emoji' => '🌌', 'label' => 'Galaxy'],
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(LiveBroadcast::class, 'broadcast_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Get gift info by type
     */
    public static function getGiftInfo(string $type): ?array
    {
        return self::GIFT_TYPES[$type] ?? null;
    }

    /**
     * Get all available gift types
     */
    public static function getAllGiftTypes(): array
    {
        return self::GIFT_TYPES;
    }
}
