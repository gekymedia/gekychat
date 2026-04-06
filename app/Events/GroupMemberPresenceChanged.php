<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a group member becomes online or offline (app heartbeat / disconnect).
 * Clients subscribed with Echo.private('group.{id}') receive this as .GroupMemberPresenceChanged
 * so they can refresh group member presence without polling.
 */
class GroupMemberPresenceChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public int $groupId;

    public int $userId;

    /**
     * 'online' or 'offline'
     */
    public string $status;

    public function __construct(int $groupId, int $userId, string $status)
    {
        $this->groupId = $groupId;
        $this->userId = $userId;
        $this->status = $status;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('group.' . $this->groupId);
    }

    public function broadcastAs(): string
    {
        return 'GroupMemberPresenceChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'group_id' => $this->groupId,
            'user_id' => $this->userId,
            'status' => $this->status,
            'is_online' => $this->status === 'online',
            'is_group' => true,
        ];
    }
}
