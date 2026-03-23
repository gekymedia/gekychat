<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a new viewer row is created (first join) so the host can see activity.
 */
class LiveBroadcastViewerJoined implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $broadcastId,
        public array $viewer,
        public int $viewersCount,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('live-broadcast.'.$this->broadcastId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'viewer.joined';
    }

    public function broadcastWith(): array
    {
        return [
            'viewer' => $this->viewer,
            'viewers_count' => $this->viewersCount,
        ];
    }
}
