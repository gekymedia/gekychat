<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $group;
    public $updateType; // 'info', 'members', 'avatar', 'settings'
    public $changedData;
    public $updatedBy;

    /**
     * Create a new event instance.
     *
     * @param \App\Models\Group $group
     * @param string $updateType
     * @param array $changedData
     * @param int $updatedBy
     */
    public function __construct($group, string $updateType, array $changedData, int $updatedBy)
    {
        $this->group = $group;
        $this->updateType = $updateType;
        $this->changedData = $changedData;
        $this->updatedBy = $updatedBy;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('group.updates.' . $this->group->id);
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'group_id' => $this->group->id,
            'update_type' => $this->updateType,
            'changed_data' => $this->changedData,
            'updated_by' => $this->updatedBy,
            'timestamp' => now()->toDateTimeString(),
            'group' => [
                'name' => $this->group->name,
                'avatar' => $this->group->avatar_url,
                'members_count' => $this->group->members->count(),
            ]
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'group.updated';
    }

    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function broadcastWhen()
    {
        return !empty($this->group) && !empty($this->updateType);
    }
}