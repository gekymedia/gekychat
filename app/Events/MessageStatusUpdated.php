<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $messageId,
        public string $status,
        public ?int $conversationId = null,
        public ?int $groupId = null
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        if ($this->groupId) {
            // ✅ FIXED: Use PrivateChannel for groups (matches your channels.php)
            return new PrivateChannel('group.' . $this->groupId);
        }
        
        return new PrivateChannel('conversation.' . $this->conversationId);
    }

    public function broadcastAs(): string
    {
        // ✅ FIXED: Match what ChatCore.js is listening for
        return 'MessageStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'status' => $this->status,
            'conversation_id' => $this->conversationId,
            'group_id' => $this->groupId,
            'is_group' => !is_null($this->groupId),
            'timestamp' => now()->toISOString(),
            'user_id' => auth()->id(), // ✅ ADD: Include who updated the status
        ];
    }

    public function broadcastWhen(): bool
    {
        return !is_null($this->conversationId) || !is_null($this->groupId);
    }
}