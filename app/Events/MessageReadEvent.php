<?php
namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageReadEvent implements ShouldBroadcast
{
    public function __construct(
        public int $conversationId,
        public int $messageId,
        public int $readerId
    ) {}

    public function broadcastOn() { return new PrivateChannel('chat.'.$this->conversationId); }
    public function broadcastAs() { return 'Read'; }
    public function broadcastWith() { return ['message_id'=>$this->messageId,'reader_id'=>$this->readerId]; }
}
