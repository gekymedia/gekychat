<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReacted implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public int $messageId;
    public int $userId;
    public string $reaction;
    public ?int $conversationId;
    public ?int $groupId;

    public function __construct(int $messageId, int $userId, string $reaction, ?int $conversationId = null, ?int $groupId = null)
    {
        $this->messageId = $messageId;
        $this->userId = $userId;
        $this->reaction = $reaction;
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
        return 'message.reacted';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'user_id' => $this->userId,
            'reaction' => $this->reaction,
            'is_group' => !is_null($this->groupId),
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}