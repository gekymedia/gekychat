<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Viewer tapped like (double-tap). Broadcast to everyone watching the room.
 */
class LiveBroadcastLikeSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $broadcastId,
        public array $sender,
        public int $likesCount,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('live-broadcast.' . $this->broadcastId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'like.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'sender' => $this->sender,
            'likes_count' => $this->likesCount,
        ];
    }
}
