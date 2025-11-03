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
        // Send update events over the private group channel. The presence
        // channel (presence-group.{id}) is used separately for member lists.
        return new \Illuminate\Broadcasting\PrivateChannel('group.' . $this->group->id);
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
        // Use an explicit, PascalCase name so that ChatCore.js can listen
        // for `.GroupUpdated` when group metadata changes.
        return 'GroupUpdated';
    }

    public function broadcastWhen()
    {
        return !empty($this->group) && !empty($this->updateType);
    }
}