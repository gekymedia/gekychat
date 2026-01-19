<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $conversationId,
        public array $updates = []
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversation.' . $this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'updates' => $this->updates,
        ];
    }
}
