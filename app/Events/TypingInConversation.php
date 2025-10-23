<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TypingInConversation implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $conversationId,
        public int $userId,
        public bool $isTyping
    ) {}

    public function broadcastOn() { 
        return new PrivateChannel('chat.' . $this->conversationId); 
    }
    
    public function broadcastAs() { 
        return 'user.typing'; // ✅ Consistent naming
    }
    
    public function broadcastWith() { 
        return [
            'user_id' => $this->userId, 
            'is_typing' => $this->isTyping,
            'is_group' => false
        ];
    }
}