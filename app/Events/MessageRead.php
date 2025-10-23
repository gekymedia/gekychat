<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $conversationId;
    public $readerId;
    public $messageIds;

    public function __construct($conversationId, $readerId, $messageIds = [])
    {
        $this->conversationId = $conversationId;
        $this->readerId = $readerId;
        $this->messageIds = $messageIds;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('chat.' . $this->conversationId);
    }

    public function broadcastWith()
    {
        return [
            'reader_id' => $this->readerId,
            'message_ids' => $this->messageIds,
            'is_group' => false
        ];
    }

    public function broadcastAs()
    {
        return 'message.read';
    }
}