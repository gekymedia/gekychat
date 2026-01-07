<?php

namespace App\Events;

use App\Models\LiveBroadcastChat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PHASE 2: Live Broadcast Chat Sent Event
 */
class LiveBroadcastChatSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chatMessage;

    public function __construct(LiveBroadcastChat $chatMessage)
    {
        $this->chatMessage = $chatMessage->load('user');
    }

    public function broadcastOn()
    {
        return new Channel('live-broadcast.' . $this->chatMessage->broadcast_id);
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->chatMessage->id,
            'user_id' => $this->chatMessage->user_id,
            'user_name' => $this->chatMessage->user->name,
            'message' => $this->chatMessage->message,
            'created_at' => $this->chatMessage->created_at->toIso8601String(),
        ];
    }
}
