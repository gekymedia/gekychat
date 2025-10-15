<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TypingStopped implements ShouldBroadcast
{
    public function __construct(
        public int $conversationId,
        public int $userId
    ) {}

    public function broadcastOn(): PrivateChannel {
        return new PrivateChannel("private-conversation.{$this->conversationId}");
    }

    public function broadcastAs(): string {
        return 'typing.stopped';
    }

    public function broadcastWith(): array {
        return ['conversation_id' => $this->conversationId, 'user_id' => $this->userId];
    }
}
