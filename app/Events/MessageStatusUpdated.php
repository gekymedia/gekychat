<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public int $messageId;
    public string $status;
    public ?int $conversationId;
    public ?int $groupId;

    public function __construct(int $messageId, string $status, ?int $conversationId = null, ?int $groupId = null)
    {
        $this->messageId = $messageId;
        $this->status = $status;
        $this->conversationId = $conversationId;
        $this->groupId = $groupId;
    }

    public function broadcastOn(): Channel
    {
        return $this->groupId
            ? new PresenceChannel('group.' . $this->groupId)
            : new PrivateChannel('chat.' . $this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'message.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'status' => $this->status,
            'is_group' => !is_null($this->groupId),
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}