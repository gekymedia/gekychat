<?php

namespace App\Events;
// app/Events/GroupTyping.php
namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class GroupTyping implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $groupId;
    public User $user;
    public bool $isTyping;

    public function __construct(int $groupId, User $user, bool $isTyping)
    {
        $this->groupId  = $groupId;
        $this->user     = $user;       // <-- Must be a User model, not an array
        $this->isTyping = $isTyping;
    }

    public function broadcastOn()
    {
        return new PresenceChannel("group.{$this->groupId}");
    }

    public function broadcastAs(): string
    {
        return 'GroupTyping';
    }

    public function broadcastWith(): array
    {
        return [
            'user' => [
                'id'         => $this->user->id,
                'name'       => $this->user->name ?? $this->user->phone,
                'avatar_url' => $this->user->avatar_path
                    ? asset('storage/'.$this->user->avatar_path)
                    : null,
            ],
            'is_typing' => $this->isTyping,
        ];
    }
}
