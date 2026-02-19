<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Services\EventBroadcaster;

/**
 * ✅ MODERN: WhatsApp-style message delivered event
 * Broadcasts when a message reaches the recipient's device
 * Enables double gray checkmark (✓✓ delivered) UI
 */
class MessageDelivered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $conversation;
    public $messageIds;
    public $deliveredAt;
    public $userId; // User who received the messages

    /**
     * Create a new event instance.
     *
     * @param Conversation $conversation
     * @param array $messageIds Array of message IDs that were delivered
     * @param \DateTime $deliveredAt
     * @param int $userId User who received the messages
     */
    public function __construct(Conversation $conversation, array $messageIds, $deliveredAt, int $userId)
    {
        $this->conversation = $conversation;
        $this->messageIds = $messageIds;
        $this->deliveredAt = $deliveredAt;
        $this->userId = $userId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel
     */
    public function broadcastOn(): Channel
    {
        // Broadcast to conversation channel so all participants get delivery updates
        return new Channel('conversation.' . $this->conversation->id);
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'message.delivered';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return array_merge(EventBroadcaster::envelope(), [
            'conversation_id' => $this->conversation->id,
            'message_ids' => $this->messageIds,
            'delivered_at' => $this->deliveredAt->toIso8601String(),
            'user_id' => $this->userId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
