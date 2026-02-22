<?php

namespace App\Events;

use App\Models\CallSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts call invite to callee's private channel (all their devices)
 * This ensures all logged-in devices receive the call notification
 */
class CallInvite implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public CallSession $call;
    public array $caller;
    public string $type;
    public ?int $conversationId;
    public ?int $groupId;

    public function __construct(CallSession $call, array $caller)
    {
        $this->call = $call;
        $this->caller = $caller;
        $this->type = $call->type;
        $this->conversationId = $call->conversation_id;
        $this->groupId = $call->group_id;
    }

    /**
     * Broadcast to callee's private user channel (all their devices).
     * Laravel prepends "private-" so use 'user.{id}' to get channel "private-user.{id}".
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->call->callee_id);
    }

    public function broadcastAs(): string
    {
        return 'CallInvite';
    }

    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->call->id,
            'session_id' => $this->call->id,
            'caller' => $this->caller,
            'type' => $this->type,
            'conversation_id' => $this->conversationId,
            'group_id' => $this->groupId,
            'action' => 'invite',
            'call_link' => $this->call->conversation_id 
                ? \App\Models\Conversation::find($this->call->conversation_id)?->call_link
                : ($this->call->group_id 
                    ? \App\Models\Group::find($this->call->group_id)?->call_link 
                    : null),
        ];
    }
}
