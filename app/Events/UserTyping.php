<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ?int $conversationId,
        public ?int $groupId,
        public int $userId,
        public bool $isTyping
    ) {
        // Load user info for the broadcast
        $this->user = User::find($userId);
    }
    
    public $user;

    // ✅ Use only PrivateChannel for consistency
    public function broadcastOn(): PrivateChannel
    {
        if ($this->groupId) {
            return new PrivateChannel('group.' . $this->groupId);
        }
        
        return new PrivateChannel('conversation.' . $this->conversationId);
    }
    
    public function broadcastAs(): string
    {
        return 'UserTyping'; // Changed to match your frontend expectation
    }
    
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'user_name' => $this->user->name ?? $this->user->phone ?? 'User',
            'is_typing' => $this->isTyping,
            'conversation_id' => $this->conversationId,
            'group_id' => $this->groupId,
        ];
    }
}