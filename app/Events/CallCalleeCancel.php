<?php

namespace App\Events;

use App\Models\CallSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a user's other devices to stop ringing (answered on one device).
 * Broadcasts to call.{userId} and private-user.{userId} so all clients dismiss incoming UI.
 */
class CallCalleeCancel implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CallSession $call,
        public int $targetUserId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('call.' . $this->targetUserId),
            new PrivateChannel('user.' . $this->targetUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CallSignal';
    }

    public function broadcastWith(): array
    {
        return [
            'payload' => json_encode([
                'action'     => 'cancel',
                'session_id' => $this->call->id,
                'call_id'    => $this->call->id,
            ]),
            'call_id'    => $this->call->id,
            'caller_id'  => $this->call->caller_id,
            'callee_id'  => $this->call->callee_id,
            'group_id'   => $this->call->group_id,
            'type'       => $this->call->group_id ? 'group' : 'direct',
        ];
    }
}
