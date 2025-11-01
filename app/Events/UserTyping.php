<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTyping implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ?int $conversationId,
        public ?int $groupId,
        public int $userId,
        public bool $isTyping
    ) {}

    // ✅ Use only PrivateChannel for consistency
    public function broadcastOn(): PrivateChannel
    {
        if ($this->groupId) {
            return new PrivateChannel('group.' . $this->groupId);
        }
        
        return new PrivateChannel('conversation.' . $this->conversationId);
    }
    
    public function broadcastAs(): string
    {
        return 'user.typing';
    }
    
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'is_typing' => $this->isTyping,
            'is_group' => !is_null($this->groupId),
        ];
    }
}