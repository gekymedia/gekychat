<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StatusViewed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $statusId;
    public $viewerId;

    public function __construct($statusId, $viewerId)
    {
        $this->statusId = $statusId;
        $this->viewerId = $viewerId;
    }

    public function broadcastOn()
    {
        return new Channel("status.{$this->statusId}");
    }

    public function broadcastAs()
    {
        return 'status.viewed';
    }
}
