<?php

namespace App\Events;

use App\Models\Status;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StatusCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $status;

    public function __construct(Status $status)
    {
        $this->status = $status->load('user');
    }

    public function broadcastOn()
    {
        return new Channel('status.updates');
    }

    public function broadcastAs()
    {
        return 'status.created';
    }
}
