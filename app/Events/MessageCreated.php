<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageCreated implements ShouldBroadcast
{
    public function __construct(
        public int $conversationId,
        public array $messagePayload // send compact message fields
    ) {}

    public function broadcastOn(): PrivateChannel {
        return new PrivateChannel("private-conversation.{$this->conversationId}");
    }

    public function broadcastAs(): string {
        return 'message.created';
    }

    public function broadcastWith(): array {
        return ['conversation_id' => $this->conversationId, 'message' => $this->messagePayload];
    }
}
