<?php

namespace App\Events;

use App\Models\LiveBroadcast;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts when a live broadcast starts
 * Notifies all connected users about the new live broadcast
 */
class LiveBroadcastStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public LiveBroadcast $broadcast;

    public function __construct(LiveBroadcast $broadcast)
    {
        $this->broadcast = $broadcast->load('broadcaster');
    }

    /**
     * Broadcast on a public channel so all users can see new broadcasts
     */
    public function broadcastOn(): Channel
    {
        return new Channel('live-broadcasts');
    }

    public function broadcastAs(): string
    {
        return 'LiveBroadcastStarted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->broadcast->id,
            'slug' => $this->broadcast->slug,
            'title' => $this->broadcast->title,
            'broadcaster_id' => $this->broadcast->broadcaster_id,
            'broadcaster' => [
                'id' => $this->broadcast->broadcaster->id,
                'name' => $this->broadcast->broadcaster->name,
                'username' => $this->broadcast->broadcaster->username,
                'avatar_url' => $this->broadcast->broadcaster->avatar_url,
            ],
            'status' => $this->broadcast->status,
            'started_at' => $this->broadcast->started_at->toIso8601String(),
            'viewer_count' => 0, // Will be updated separately if needed
        ];
    }
}
