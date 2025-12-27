<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserAddedToGroup implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $group;
    public $addedBy;
    public $userId;

    public function __construct($group, $addedBy, $userId)
    {
        $this->group = $group;
        $this->addedBy = $addedBy;
        $this->userId = $userId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->userId);
    }

    public function broadcastWith()
    {
        return [
            'group_id' => $this->group->id,
            'update_type' => 'members_added',
            'changed_data' => [
                'member_ids' => [$this->userId],
                'count' => 1,
                'added_by' => [
                    'id' => $this->addedBy->id,
                    'name' => $this->addedBy->name ?? $this->addedBy->phone,
                ]
            ],
            'updated_by' => $this->addedBy->id,
            'timestamp' => now()->toDateTimeString(),
            'group' => [
                'id' => $this->group->id,
                'name' => $this->group->name,
                'slug' => $this->group->slug,
                'avatar' => $this->group->avatar_url,
                'type' => $this->group->type,
                'members_count' => $this->group->members->count(),
            ]
        ];
    }

    public function broadcastAs()
    {
        return 'GroupUpdated';
    }

    public function broadcastWhen()
    {
        return !empty($this->group) && !empty($this->addedBy);
    }
}
