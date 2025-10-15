<?php
namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class DmMessageSent implements ShouldBroadcast {
    use SerializesModels;
    public function __construct(public Message $message) {}

    public function broadcastOn() {
        return new PrivateChannel('private-conversation.'.$this->message->conversation_id);
    }
    public function broadcastAs() { return 'DmMessageSent'; }
    public function broadcastWith(): array {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
