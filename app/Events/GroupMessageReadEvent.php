<?php
namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class GroupMessageReadEvent implements ShouldBroadcast
{
    public function __construct(
        public int $groupId,
        public int $messageId,
        public int $readerId
    ) {}

    public function broadcastOn() { return new PresenceChannel('group.'.$this->groupId); }
    public function broadcastAs() { return 'Read'; }
    public function broadcastWith() { return ['message_id'=>$this->messageId,'reader_id'=>$this->readerId]; }
}
