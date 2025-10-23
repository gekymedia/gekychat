<?php

namespace App\Events;

use App\Models\GroupMessage;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageEdited implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $message;

    public function __construct(GroupMessage $message)
    {
        $this->message = $message->loadMissing(['sender', 'attachments', 'replyTo.sender', 'group']);
    }

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('group.' . $this->message->group_id);
    }

    public function broadcastAs(): string
    {
        return 'message.edited';
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