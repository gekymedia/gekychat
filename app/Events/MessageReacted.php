<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class MessageReacted implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    /**
     * The reacted message ID.
     *
     * @var int
     */
    public int $message_id;

    /**
     * The user who reacted.
     *
     * @var int
     */
    public int $user_id;

    /**
     * The emoji / reaction content (e.g., 👍, ❤️).
     *
     * @var string
     */
    public string $reaction;

    /**
     * Conversation ID resolved from the message.
     *
     * @var int|null
     */
    public ?int $conversation_id;

    /**
     * Create a new event instance.
     *
     * Controller usage:
     * broadcast(new MessageReacted($message->id, Auth::id(), $reaction))->toOthers();
     */
    public function __construct(int $messageId, int $userId, string $reaction)
    {
        $this->message_id = $messageId;
        $this->user_id    = $userId;
        $this->reaction   = $reaction;

        // Resolve the conversation for broadcasting (no extra param needed in controller)
        $this->conversation_id = Message::whereKey($messageId)->value('conversation_id');
    }

    /**
     * Broadcast on the same private channel as other DM events.
     */
    public function broadcastOn(): array
    {
        // If we couldn't resolve the conversation, don't broadcast.
        if (empty($this->conversation_id)) {
            return [];
        }

        return [new PrivateChannel('chat.' . $this->conversation_id)];
    }

    /**
     * Optional: set a custom event name used on the client.
     */
    public function broadcastAs(): string
    {
        return 'MessageReacted';
    }

    /**
     * Payload sent to the client.
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message_id,
            'user_id'    => $this->user_id,
            'reaction'   => $this->reaction,
        ];
    }
}
