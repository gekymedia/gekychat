<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $groupId;
    public int $messageId;
    public int $deletedBy;

    /**
     * @param  int  $groupId
     * @param  int  $messageId
     * @param  int  $deletedBy  The user ID who deleted the message
     */
    public function __construct(int $groupId, int $messageId, int $deletedBy)
    {
        $this->groupId   = $groupId;
        $this->messageId = $messageId;
        $this->deletedBy = $deletedBy;
    }

    public function broadcastOn(): PrivateChannel
    {
        // Make sure your Echo client listens on: Echo.private(`group.${groupId}`)
        return new PrivateChannel("group.{$this->groupId}");
    }

    public function broadcastAs(): string
    {
        return 'GroupMessageDeleted';
    }

    public function broadcastWith(): array
    {
        return [
            'group_id'   => $this->groupId,
            'message_id' => $this->messageId,
            'deleted_by' => $this->deletedBy,
        ];
    }
}
