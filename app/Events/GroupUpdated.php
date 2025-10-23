<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $group;
    public $updateType;
    public $changedData;
    public $updatedBy;

    public function __construct($group, string $updateType, array $changedData, int $updatedBy)
    {
        $this->group = $group;
        $this->updateType = $updateType;
        $this->changedData = $changedData;
        $this->updatedBy = $updatedBy;
    }

    public function broadcastOn()
    {
        // ✅ FIXED: Use PresenceChannel for group updates
        return new PresenceChannel('group.' . $this->group->id);
    }

    public function broadcastWith()
    {
        return [
            'group_id' => $this->group->id,
            'update_type' => $this->updateType,
            'changed_data' => $this->changedData,
            'updated_by' => $this->updatedBy,
            'timestamp' => now()->toDateTimeString(),
            'group' => [
                'id' => $this->group->id,
                'name' => $this->group->name,
                'avatar' => $this->group->avatar_url,
                'members_count' => $this->group->members->count(),
            ]
        ];
    }

    public function broadcastAs()
    {
        return 'group.updated';
    }

    public function broadcastWhen()
    {
        return !empty($this->group) && !empty($this->updateType);
    }
}