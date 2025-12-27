<?php

namespace App\Events;

use App\Models\GroupMessage;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageEdited implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public $message;

    public function __construct(GroupMessage $message)
    {
        $this->message = $message->loadMissing(['sender', 'attachments', 'replyTo.sender', 'group']);
    }

    public function broadcastOn(): \Illuminate\Broadcasting\Channel
    {
        // Use a private channel so that Echo.private('group.{id}') receives the broadcast
        return new \Illuminate\Broadcasting\PrivateChannel('group.' . $this->message->group_id);
    }

    public function broadcastAs(): string
    {
        // Broadcast using the explicit event name so that the frontend can
        // listen to `.GroupMessageEdited` on the group channel.
        return 'GroupMessageEdited';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'body' => $this->message->body,
            'edited_at' => $this->message->edited_at?->toISOString(),
            'group_id' => $this->message->group_id,
        ];
    }
}