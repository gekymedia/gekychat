<?php

namespace App\Events;

use App\Models\LiveBroadcast;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts when a live broadcast ends
 * Notifies all connected users that the broadcast has ended
 */
class LiveBroadcastEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $broadcastId;
    public string $slug;

    public function __construct(LiveBroadcast $broadcast)
    {
        $this->broadcastId = $broadcast->id;
        $this->slug = $broadcast->slug;
    }

    /**
     * Broadcast on the same public channel
     */
    public function broadcastOn(): Channel
    {
        return new Channel('live-broadcasts');
    }

    public function broadcastAs(): string
    {
        return 'LiveBroadcastEnded';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->broadcastId,
            'slug' => $this->slug,
            'status' => 'ended',
        ];
    }
}
