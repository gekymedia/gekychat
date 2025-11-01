<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReceiptUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $conversationId,
        public int $messageId,
        public int $userId,
        public ?string $deliveredAt,
        public ?string $readAt
    ) {}

    public function broadcastOn(): PrivateChannel {
        return new PrivateChannel('conversation.' . $this->conversationId);
    }

    public function broadcastAs(): string {
        return 'message.status.updated';
    }

    public function broadcastWith(): array {
        return [
            'message_id'   => $this->messageId,
            'user_id'      => $this->userId,
            'delivered_at' => $this->deliveredAt,
            'read_at'      => $this->readAt,
            'is_group' => false
        ];
    }
}