<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTyping implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public ?int $conversationId;
    public ?int $groupId;
    public User $user;
    public bool $isTyping;

    public function __construct(?int $conversationId, ?int $groupId, User $user, bool $is_typing)
    {
        $this->conversationId = $conversationId;
        $this->groupId = $groupId;
        $this->user = $user;
        $this->isTyping = $is_typing;
    }

    public function broadcastOn(): Channel
    {
        return $this->groupId
            ? new PresenceChannel('group.' . $this->groupId)
            : new PrivateChannel('chat.' . $this->conversationId);
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
            'is_group' => !is_null($this->groupId),
        ];
    }
}