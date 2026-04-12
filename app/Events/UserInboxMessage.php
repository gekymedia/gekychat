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
        $this->message->loadMissing([
            'sender',
            'attachments',
            'referencedStatus:id,user_id,type,text,media_url,thumbnail_url,expires_at',
            'replyTo.sender',
            'forwardedFrom.sender',
        ]);
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

        $attachments = $this->message->attachments->map(function ($attachment) {
            return [
                'id' => $attachment->id,
                'url' => \App\Helpers\UrlHelper::secureStorageUrl($attachment->file_path),
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
            ];
        })->values()->all();

        $referencedStatus = null;
        if (! empty($this->message->referenced_status_id)) {
            $ref = $this->message->referencedStatus;
            if (! $ref) {
                $referencedStatus = [
                    'id' => (int) $this->message->referenced_status_id,
                    'expired' => true,
                ];
            } else {
                $referencedStatus = [
                    'id' => $ref->id,
                    'user_id' => $ref->user_id,
                    'type' => $ref->type,
                    'text' => $ref->text,
                    'media_url' => $ref->media_url,
                    'thumbnail_url' => $ref->thumbnail_url,
                    'expires_at' => $ref->expires_at?->toIso8601String(),
                    'expired' => $ref->isExpired(),
                ];
            }
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
                'reply_to_id' => $this->message->reply_to,
                'forwarded_from_id' => $this->message->forwarded_from_id,
                'referenced_status_id' => $this->message->referenced_status_id,
                'referenced_status' => $referencedStatus,
                'attachments' => $attachments,
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
