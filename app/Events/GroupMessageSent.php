<?php

namespace App\Events;

use App\Models\GroupMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public GroupMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('group.' . $this->message->group_id)];
    }

    public function broadcastAs(): string
    {
        return 'GroupMessageSent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message->load('sender', 'replyTo')
        ];
    }
}

// namespace App\Events;

// use App\Models\GroupMessage;
// use Illuminate\Broadcasting\PresenceChannel;
// use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
// use Illuminate\Queue\SerializesModels;

// class GroupMessageSent implements ShouldBroadcast {
//     use SerializesModels;
//     public function __construct(public GroupMessage $message) {}

//     public function broadcastOn() { return new PresenceChannel('presence-group.'.$this->message->group_id); }
//     public function broadcastAs() { return 'GroupMessageSent'; }
//     public function broadcastWith() {
//         return [
//             'id' => $this->message->id,
//             'group_id' => $this->message->group_id,
//             'sender_id' => $this->message->sender_id,
//             'body' => $this->message->body,
//             'created_at' => $this->message->created_at?->toIso8601String(),
//         ];
//     }
// }
