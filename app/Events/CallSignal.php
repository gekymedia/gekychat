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
 * Broadcasts signalling data for WebRTC calls. When a caller sends an
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
     * The channel(s) the event should broadcast on.
     * For 1:1 calls we must broadcast to BOTH caller and callee so each receives the other's signals (offer, answer, ICE).
     */
    public function broadcastOn(): Channel|array
    {
        if ($this->call->group_id) {
            return new PrivateChannel('group.' . $this->call->group_id . '.call');
        }

        // 1:1 call: broadcast to both parties so caller receives answer/ICE and callee receives offer/ICE
        $channels = [];
        if ($this->call->caller_id) {
            $channels[] = new PrivateChannel('call.' . $this->call->caller_id);
        }
        if ($this->call->callee_id) {
            $channels[] = new PrivateChannel('call.' . $this->call->callee_id);
        }
        return $channels ?: [new PrivateChannel('call.0')];
    }

    public function broadcastAs(): string
    {
        return 'CallSignal';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'payload' => $this->payload,
            'call_id' => $this->call->id,
            'caller_id' => $this->call->caller_id,
            'callee_id' => $this->call->callee_id,
            'group_id' => $this->call->group_id,
            'type' => $this->call->group_id ? 'group' : 'direct'
        ];
    }
}