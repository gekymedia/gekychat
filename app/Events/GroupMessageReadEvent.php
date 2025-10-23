<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageReadEvent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $groupId,
        public int $messageId,
        public int $readerId
    ) {}

    public function broadcastOn() { 
        return new PresenceChannel('group.' . $this->groupId); 
    }
    
    public function broadcastAs() { 
        return 'message.read'; // ✅ Consistent naming
    }
    
    public function broadcastWith() { 
        return [
            'message_id' => $this->messageId,
            'reader_id' => $this->readerId,
            'is_group' => true
        ];
    }
}