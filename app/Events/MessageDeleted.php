<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public int $messageId;
    public ?int $conversationId;
    public ?int $groupId;
    public int $deletedBy;

    /**
     * Create a new event instance.
     *
     * @param int $messageId
     * @param int $deletedBy
     * @param int|null $conversationId
     * @param int|null $groupId
     */
    public function __construct(
        int $messageId,
        int $deletedBy,
        ?int $conversationId = null,
        ?int $groupId = null
    ) {
        $this->messageId = $messageId;
        $this->deletedBy = $deletedBy;
        $this->conversationId = $conversationId;
        $this->groupId = $groupId;

        if (is_null($conversationId) && is_null($groupId)) {
            throw new \InvalidArgumentException('Either conversationId or groupId must be provided');
        }
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return PrivateChannel
     */
    public function broadcastOn(): PrivateChannel
    {
        return $this->groupId
            ? new PrivateChannel('group.' . $this->groupId)
            : new PrivateChannel('chat.' . $this->conversationId);
    }

    /**
     * Get the event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'message.deleted';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'deleted_by' => $this->deletedBy,
            'is_group' => !is_null($this->groupId),
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function broadcastWhen(): bool
    {
        return !is_null($this->conversationId) || !is_null($this->groupId);
    }
}