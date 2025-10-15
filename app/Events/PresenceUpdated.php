<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PresenceUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $userId;
    public $groupId;
    public $status; // 'online', 'offline', 'away'
    public $lastActive;

    /**
     * Create a new event instance.
     *
     * @param int $userId
     * @param int|null $groupId
     * @param string $status
     * @param string|null $lastActive
     */
    public function __construct(int $userId, ?int $groupId, string $status, ?string $lastActive = null)
    {
        $this->userId = $userId;
        $this->groupId = $groupId;
        $this->status = $status;
        $this->lastActive = $lastActive ?? now()->toDateTimeString();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return $this->groupId
            ? new PrivateChannel('group.presence.' . $this->groupId)
            : new PrivateChannel('user.presence.' . $this->userId);
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'user_id' => $this->userId,
            'status' => $this->status,
            'last_active' => $this->lastActive,
            'is_group' => (bool)$this->groupId,
            'group_id' => $this->groupId,
            'timestamp' => now()->toDateTimeString()
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'presence.updated';
    }

    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function broadcastWhen()
    {
        return in_array($this->status, ['online', 'offline', 'away']);
    }
}