<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class UserTyping implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(
        public ?int $conversationId = null,
        public ?int $groupId = null,
        public $user = null,
        public bool $is_typing = false
    ) {}

    public function broadcastOn()
    {
        return $this->groupId
            ? new PrivateChannel('group.' . $this->groupId)
            : new PrivateChannel('chat.' . $this->conversationId);
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'   => $this->user?->id,
            'is_typing' => $this->is_typing,
        ];
    }

    public function broadcastAs(): string
    {
        return 'UserTyping';
    }
}
