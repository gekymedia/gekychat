<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $message;

    public function __construct(string $message = 'Test message')
    {
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('test-channel'), // Public channel (no auth required)
        ];
    }

    public function broadcastAs(): string
    {
        return 'TestEvent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'time' => now()->toDateTimeString()
        ];
    }
}