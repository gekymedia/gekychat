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
     * The channel the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        // For 1:1 calls, broadcast to the conversation. For group calls, broadcast to the group.
        if ($this->call->group_id) {
            return new PrivateChannel('group.' . $this->call->group_id . '.call');
        }
        
        // ✅ CHANGED: Use conversation.{id} for direct calls instead of call.{user_id}
        // Assuming your CallSession has a conversation_id for 1:1 calls
        if ($this->call->conversation_id) {
            return new PrivateChannel('conversation.' . $this->call->conversation_id);
        }
        
        // Fallback: If no conversation_id, use the old call.{user_id} channel
        // but ideally you should add conversation_id to your CallSession model
        return new PrivateChannel('call.' . $this->call->callee_id);
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
            'conversation_id' => $this->call->conversation_id,
            'group_id' => $this->call->group_id,
            'type' => $this->call->group_id ? 'group' : 'direct'
        ];
    }
}