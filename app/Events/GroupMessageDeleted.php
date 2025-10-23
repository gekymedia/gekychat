<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public int $groupId;
    public int $messageId;
    public int $deletedBy;

    public function __construct(int $groupId, int $messageId, int $deletedBy)
    {
        $this->groupId = $groupId;
        $this->messageId = $messageId;
        $this->deletedBy = $deletedBy;
    }

    public function broadcastOn(): PresenceChannel
    {
        // ✅ Use PresenceChannel for groups
        return new PresenceChannel('group.' . $this->groupId);
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
            'is_group' => true,
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}