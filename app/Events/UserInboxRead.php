<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sync read state across all sessions of the same user (phone + web + desktop).
 * Unlike MessageRead (toOthers), this reaches every device logged in as the reader.
 */
class UserInboxRead implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public ?int $conversationId = null,
        public ?int $groupId = null,
        public int $unreadCount = 0,
        public array $messageIds = [],
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'UserInboxRead';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'conversation_id' => $this->conversationId,
            'group_id' => $this->groupId,
            'unread_count' => $this->unreadCount,
            'message_ids' => $this->messageIds,
            'read_at' => now()->toISOString(),
        ];
    }
}
