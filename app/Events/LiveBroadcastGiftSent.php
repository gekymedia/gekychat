<?php

namespace App\Events;

use App\Models\LiveBroadcastGift;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a gift is sent during a live broadcast.
 * Viewers receive this to show gift animations.
 */
class LiveBroadcastGiftSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LiveBroadcastGift $gift
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('live-broadcast.' . $this->gift->broadcast_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'gift.sent';
    }

    public function broadcastWith(): array
    {
        $sender = $this->gift->sender;
        $giftInfo = LiveBroadcastGift::getGiftInfo($this->gift->gift_type);
        
        return [
            'id' => $this->gift->id,
            'gift_type' => $this->gift->gift_type,
            'coins' => $this->gift->coins,
            'emoji' => $giftInfo['emoji'] ?? '🎁',
            'label' => $giftInfo['label'] ?? 'Gift',
            'message' => $this->gift->message,
            'sender' => [
                'id' => $sender->id,
                'name' => $sender->name,
                'username' => $sender->username,
                'avatar_url' => $sender->avatar_url,
            ],
            'created_at' => $this->gift->created_at->toIso8601String(),
        ];
    }
}
