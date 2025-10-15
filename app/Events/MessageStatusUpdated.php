<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class MessageStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $messageId;
    public $status;
    public $isGroup;
    public $conversationId;
    public $groupId;

    public function __construct(
        int $messageId, 
        string $status, 
        bool $isGroup = false,
        ?int $conversationId = null,
        ?int $groupId = null
    ) {
        $this->messageId = $messageId;
        $this->status = $status;
        $this->isGroup = $isGroup;
        $this->conversationId = $conversationId;
        $this->groupId = $groupId;
    }

    public function broadcastOn()
    {
        return $this->isGroup
            ? new PrivateChannel('group.'.$this->groupId)
            : new PrivateChannel('chat.'.$this->conversationId);
    }

    public function broadcastWith()
    {
        return [
            'message_id' => $this->messageId,
            'status' => $this->status,
            'updated_at' => now()->toISOString(),
            'is_group' => $this->isGroup,
        ];
    }

    public function broadcastAs()
    {
        return 'message.status.updated';
    }

    public function broadcastWhen()
    {
        return in_array($this->status, ['sent', 'delivered', 'read', 'failed'])
            && ($this->isGroup ? !is_null($this->groupId) : !is_null($this->conversationId));
    }
}