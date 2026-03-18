<?php

namespace App\Events;

use App\Models\CallSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts to the callee only: "call was answered (e.g. on another device)".
 * Callee's other devices use this to stop ringing and dismiss the incoming call UI.
 */
class CallCalleeCancel implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CallSession $call
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('call.' . $this->call->callee_id);
    }

    public function broadcastAs(): string
    {
        return 'CallSignal';
    }

    public function broadcastWith(): array
    {
        return [
            'payload' => json_encode([
                'action'    => 'cancel',
                'session_id' => $this->call->id,
                'call_id'   => $this->call->id,
            ]),
            'call_id'    => $this->call->id,
            'caller_id'  => $this->call->caller_id,
            'callee_id'  => $this->call->callee_id,
            'group_id'   => $this->call->group_id,
            'type'       => $this->call->group_id ? 'group' : 'direct',
        ];
    }
}
