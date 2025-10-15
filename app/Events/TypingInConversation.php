<?php
namespace App\Events;


use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;


class TypingInConversation implements ShouldBroadcast
{
public function __construct(
public int $conversationId,
public int $userId,
public bool $isTyping
) {}


public function broadcastOn() { return new PrivateChannel('chat.'.$this->conversationId); }
public function broadcastAs() { return 'Typing'; }
public function broadcastWith() { return ['user_id'=>$this->userId, 'is_typing'=>$this->isTyping]; }
}