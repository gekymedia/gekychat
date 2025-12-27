<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

/**
 * TypingInGroup event is dispatched whenever a user starts or stops
 * typing in a group chat. The event name is explicitly exposed as
 * `TypingInGroup` so that the frontend can listen for
 * `.TypingInGroup` on group channels (see ChatCore.js).
 */
class TypingInGroup implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * The identifier of the group where the typing event occurred.
     */
    public int $groupId;

    /**
     * The identifier of the user who is typing or not typing.
     */
    public int $userId;

    /**
     * Whether the user is currently typing (true) or has stopped (false).
     */
    public bool $isTyping;
    
    /**
     * The user model for name lookup.
     */
    public $user;

    /**
     * Create a new event instance.
     */
    public function __construct(int $groupId, int $userId, bool $isTyping)
    {
        $this->groupId = $groupId;
        $this->userId = $userId;
        $this->isTyping = $isTyping;
        // Load user info for the broadcast
        $this->user = User::find($userId);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): PrivateChannel
    {
        // Broadcast to the private group channel. Even though groups are
        // presence channels in channels.php, using PrivateChannel here
        // allows Echo.private('group.{id}') to receive the event.
        return new PrivateChannel('group.' . $this->groupId);
    }

    /**
     * Get the event name to broadcast as. Using a PascalCase name
     * matches the convention in ChatCore.js listeners (e.g. `.TypingInGroup`).
     */
    public function broadcastAs(): string
    {
        return 'TypingInGroup';
    }

    /**
     * Get the data to broadcast with the event.
     */
    public function broadcastWith(): array
    {
        return [
            'group_id'  => $this->groupId,
            'user_id'   => $this->userId,
            'user_name' => $this->user->name ?? $this->user->phone ?? 'User',
            'is_typing' => $this->isTyping,
            'is_group'  => true,
        ];
    }
}