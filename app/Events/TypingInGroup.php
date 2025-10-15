<?php
namespace App\Events;


use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;


class TypingInGroup implements ShouldBroadcast
{
public function __construct(
public int $groupId,
public int $userId,
public bool $isTyping
) {}


public function broadcastOn() { return new PresenceChannel('group.'.$this->groupId); }
public function broadcastAs() { return 'Typing'; }
public function broadcastWith() { return ['user_id'=>$this->userId, 'is_typing'=>$this->isTyping]; }
}