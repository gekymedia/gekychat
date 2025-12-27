<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupTyping implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public int $groupId;
    public User $user;
    public bool $isTyping;

    public function __construct(int $groupId, User $user, bool $isTyping)
    {
        $this->groupId = $groupId;
        $this->user = $user;
        $this->isTyping = $isTyping;
    }

    public function broadcastOn(): \Illuminate\Broadcasting\Channel
    {
        // Broadcast on the private group channel; presence tracking is handled
        // via separate presence-group channels.
        return new \Illuminate\Broadcasting\PrivateChannel('group.' . $this->groupId);
    }

    public function broadcastAs(): string
    {
        // Broadcast the descriptive event name that the frontend listens to
        // on group channels (see ChatCore.js `.GroupTyping`).
        return 'GroupTyping';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name ?? $this->user->phone,
            'is_typing' => $this->isTyping,
            'is_group' => true,
        ];
    }
}