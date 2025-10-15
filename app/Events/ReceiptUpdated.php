<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ReceiptUpdated implements ShouldBroadcast
{
    protected int $conversationId;

    public function __construct(
        public int $messageId,
        public int $userId,
        public ?string $deliveredAt,
        public ?string $readAt
    ) {
        $this->conversationId = (int) optional(Message::find($messageId))->conversation_id;
    }

    public function broadcastOn(): PrivateChannel {
        return new PrivateChannel("private-conversation.{$this->conversationId}");
    }

    public function broadcastAs(): string {
        return 'receipt.updated';
    }

    public function broadcastWith(): array {
        return [
            'message_id'   => $this->messageId,
            'user_id'      => $this->userId,
            'delivered_at' => $this->deliveredAt,
            'read_at'      => $this->readAt,
        ];
    }
}
