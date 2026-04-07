<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserInboxMessage implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Message $message,
        public int $recipientId
    ) {
        $this->message->loadMissing(['sender', 'attachments']);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->recipientId);
    }

    public function broadcastAs(): string
    {
        return 'UserInboxMessage';
    }

    public function broadcastWith(): array
    {
        $sender = $this->message->sender;
        $body = (string) ($this->message->body ?? '');
        $serverSentAtMs = (int) round(microtime(true) * 1000);
        if ($this->message->is_encrypted) {
            $body = $this->message->attachments->isNotEmpty()
                ? '📎 Attachment'
                : '[Encrypted Message]';
        }

        return [
            'event_v' => 1,
            'ts_ms' => $serverSentAtMs,
            'server_sent_at_ms' => $serverSentAtMs,
            'event_type' => 'user.inbox.message',
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'cid' => $this->message->conversation_id,
                'sender_id' => $this->message->sender_id,
                'sid' => $this->message->sender_id,
                'body' => $body,
                'created_at' => $this->message->created_at?->toISOString(),
                'is_group' => false,
                'has_attachments' => $this->message->attachments->isNotEmpty(),
                'sender' => [
                    'id' => $sender?->id,
                    'name' => $sender?->name ?? $sender?->phone ?? 'Someone',
                    'avatar_url' => $sender?->avatar_path
                        ? \App\Helpers\UrlHelper::secureStorageUrl($sender->avatar_path)
                        : null,
                ],
            ],
        ];
    }
}
