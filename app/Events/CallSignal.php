<?php

namespace App\Events;

use App\Models\CallSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts signalling data for WebRTC calls.  When a caller sends an
 * offer/answer/ICE candidate, this event pushes the payload to the intended
 * recipient(s) via a private channel.
 */
class CallSignal implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $payload;
    public CallSession $call;

    /**
     * Create a new event instance.
     */
    public function __construct(CallSession $call, string $payload)
    {
        $this->call = $call;
        $this->payload = $payload;
    }

    /**
     * The channel the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        // For 1:1 calls, broadcast to the callee.  For group calls, broadcast to the group.
        if ($this->call->group_id) {
            return new PrivateChannel('group.' . $this->call->group_id . '.call');
        }
        return new PrivateChannel('call.' . $this->call->callee_id);
    }

    public function broadcastAs(): string
    {
        return 'CallSignal';
    }
}