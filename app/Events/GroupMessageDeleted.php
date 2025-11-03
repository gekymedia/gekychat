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

    public function broadcastOn(): \Illuminate\Broadcasting\Channel
    {
        // Broadcast on a private group channel so that Echo.private('group.{id}')
        // will receive the deletion event.
        return new \Illuminate\Broadcasting\PrivateChannel('group.' . $this->groupId);
    }

    public function broadcastAs(): string
    {
        // Frontend listens for `.GroupMessageDeleted` on group channels
        // (see ChatCore.js), so broadcast the event name accordingly.
        return 'GroupMessageDeleted';
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