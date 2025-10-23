<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public int $messageId;
    public ?int $conversationId;
    public ?int $groupId;
    public int $deletedBy;

    public function __construct(
        int $messageId,
        int $deletedBy,
        ?int $conversationId = null,
        ?int $groupId = null
    ) {
        $this->messageId = $messageId;
        $this->deletedBy = $deletedBy;
        $this->conversationId = $conversationId;
        $this->groupId = $groupId;

        if (is_null($conversationId) && is_null($groupId)) {
            throw new \InvalidArgumentException('Either conversationId or groupId must be provided');
        }
    }

    public function broadcastOn(): Channel
    {
        // ✅ FIXED: Use proper channel types
        return $this->groupId
            ? new PresenceChannel('group.' . $this->groupId)
            : new PrivateChannel('chat.' . $this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'deleted_by' => $this->deletedBy,
            'is_group' => !is_null($this->groupId),
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    public function broadcastWhen(): bool
    {
        return !is_null($this->conversationId) || !is_null($this->groupId);
    }
}