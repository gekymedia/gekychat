<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupTyping implements ShouldBroadcast
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

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('group.' . $this->groupId);
    }

    public function broadcastAs(): string
    {
        return 'user.typing';
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