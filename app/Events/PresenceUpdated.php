<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PresenceUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $userId;
    public $groupId;
    public $status;
    public $lastActive;

    public function __construct(int $userId, ?int $groupId, string $status, ?string $lastActive = null)
    {
        $this->userId = $userId;
        $this->groupId = $groupId;
        $this->status = $status;
        $this->lastActive = $lastActive ?? now()->toDateTimeString();
    }

    public function broadcastOn()
    {
        // ✅ FIXED: Use proper channel types
        return $this->groupId
            ? new PresenceChannel('group.' . $this->groupId)
            : new PresenceChannel('user.presence.' . $this->userId);
    }

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

    public function broadcastAs()
    {
        return 'presence.updated';
    }

    public function broadcastWhen()
    {
        return in_array($this->status, ['online', 'offline', 'away']);
    }
}