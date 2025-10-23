<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReceiptUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

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
        // ✅ FIXED: Use correct channel name
        return new PrivateChannel('chat.' . $this->conversationId);
    }

    public function broadcastAs(): string {
        return 'message.status.updated'; // ✅ Consistent with other status events
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