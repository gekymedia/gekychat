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
        // Use a private channel so that Echo.private('group.{id}') can listen
        return new \Illuminate\Broadcasting\PrivateChannel('group.' . $this->groupId); 
    }
    
    public function broadcastAs() { 
        // Use the event class name so the frontend can listen for
        // `.GroupMessageReadEvent` on the group channel.
        return 'GroupMessageReadEvent';
    }
    
    public function broadcastWith() { 
        return [
            'message_id' => $this->messageId,
            'reader_id' => $this->readerId,
            'is_group' => true
        ];
    }
}