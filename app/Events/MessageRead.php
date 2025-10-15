<?php

// app/Events/MessageRead.php
namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageRead implements ShouldBroadcast
{
    public $conversationId;
    public $readerId;

    public function __construct($conversationId, $readerId)
    {
        $this->conversationId = $conversationId;
        $this->readerId = $readerId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('chat.' . $this->conversationId);
    }

    public function broadcastWith()
    {
        return ['reader_id' => $this->readerId];
    }
}