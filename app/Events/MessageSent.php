<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use App\Services\EventBroadcaster;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $isGroup = false;

    public function __construct(Message $message)
    {
        $this->message = $message->loadMissing([
            'sender',
            'attachments',
            'replyTo.sender',
            'forwardedFrom.sender',
            'referencedStatus:id,user_id,type,text,media_url,thumbnail_url,expires_at',
            'referencedGroupMessage.group',
        ]);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversation.' . $this->message->conversation_id);
    }

    // Event name (Phase 11: payload includes event_type: 'message.sent' for clients that use snake_case)
    public function broadcastAs()
    {
        return 'MessageSent';
    }

    public function broadcastWith()
    {
        // Don't include HTML - it exceeds Pusher's 10KB limit
        // Frontend will render the message from the data provided
        $serverSentAtMs = (int) round(microtime(true) * 1000);
        
        $bodyPlain = $this->message->body;
        if ($this->message->is_encrypted) {
            if (auth()->id() === $this->message->sender_id) {
                try {
                    $bodyPlain = Crypt::decryptString($this->message->body);
                } catch (\Exception $e) {
                    $bodyPlain = $this->message->body;
                }
            } else {
                $bodyPlain = '[Encrypted Message]';
            }
        }

        $pollData = $this->getPollDataForMessage($this->message->id, null);
        $effectiveType = $this->getMessageType($this->message);
        if ($pollData !== null) {
            $effectiveType = 'poll';
        }

        return array_merge(EventBroadcaster::envelope(), [
            'event_v' => 1,
            'ts_ms' => $serverSentAtMs,
            'server_sent_at_ms' => $serverSentAtMs,
            'event_type' => 'message.sent',
            'message' => [
                'id' => $this->message->id,
                'body' => $bodyPlain,
                'sender_id' => $this->message->sender_id,
                'conversation_id' => $this->message->conversation_id,
                'created_at' => $this->message->created_at->toISOString(),
                'is_encrypted' => $this->message->is_encrypted,
                'reply_to' => $this->message->reply_to,
                'forwarded_from_id' => $this->message->forwarded_from_id,
                'referenced_status_id' => $this->message->referenced_status_id,
                'referenced_status' => $this->referencedStatusPayload(),
                'referenced_group_id' => $this->message->referenced_group_id,
                'referenced_group_message_id' => $this->message->referenced_group_message_id,
                'referenced_group' => $this->referencedGroupPayload(),
                'view_once' => (bool) ($this->message->is_view_once ?? false),
                'view_once_opened' => $this->message->viewed_at !== null,
            ],
            'id' => $this->message->id,
            'body' => $bodyPlain,
            'sender_id' => $this->message->sender_id,
            'conversation_id' => $this->message->conversation_id,
            'created_at' => $this->message->created_at->toISOString(),
            'is_group' => false,
            'is_encrypted' => $this->message->is_encrypted,
            'type' => $effectiveType,
            'sender' => [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->name ?? $this->message->sender->phone,
                'avatar' => $this->message->sender->avatar_path ? \App\Helpers\UrlHelper::secureStorageUrl($this->message->sender->avatar_path) : null,
                'avatar_path' => $this->message->sender->avatar_path,
            ],
            'attachments' => $this->message->attachments->map(function($attachment) {
                return [
                    'id' => $attachment->id,
                    'url' => \App\Helpers\UrlHelper::secureStorageUrl($attachment->file_path),
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'type' => $this->getAttachmentType($attachment),
                    'file_path' => $attachment->file_path,
                ];
            })->toArray(),
            'reply_to' => $this->message->replyTo ? [
                'id' => $this->message->replyTo->id,
                'body' => $this->message->replyTo->body,
                'sender' => [
                    'id' => $this->message->replyTo->sender->id,
                    'name' => $this->message->replyTo->sender->name ?? $this->message->replyTo->sender->phone,
                ]
            ] : null,
            'reply_to_id' => $this->message->reply_to,
            'referenced_status_id' => $this->message->referenced_status_id,
            'referenced_status' => $this->referencedStatusPayload(),
            'referenced_group_id' => $this->message->referenced_group_id,
            'referenced_group_message_id' => $this->message->referenced_group_message_id,
            'referenced_group' => $this->referencedGroupPayload(),
            'forwarded_from' => $this->message->forwardedFrom ? [
                'id' => $this->message->forwardedFrom->id,
                'body' => $this->message->forwardedFrom->body,
                'sender' => [
                    'id' => $this->message->forwardedFrom->sender->id,
                    'name' => $this->message->forwardedFrom->sender->name ?? $this->message->forwardedFrom->sender->phone,
                ]
            ] : null,
            'link_previews' => $this->message->link_previews ?? [],
            'call_data' => $this->message->call_data ?? null, // Include call_data for call messages
            'location_data' => $this->message->location_data ?? null,
            'contact_data' => $this->message->contact_data ?? null,
            'poll_data' => $pollData,
            'metadata' => $this->message->metadata ?? null,
            'view_once' => (bool) ($this->message->is_view_once ?? false),
            'view_once_opened' => $this->message->viewed_at !== null,
        ]);
    }

    /**
     * Effective message type for clients (live_location, location, contact, poll, call, etc.).
     */
    protected function referencedStatusPayload(): ?array
    {
        if (empty($this->message->referenced_status_id)) {
            return null;
        }
        $ref = $this->message->referencedStatus;
        if (! $ref) {
            return [
                'id' => (int) $this->message->referenced_status_id,
                'user_id' => null,
                'type' => null,
                'text' => null,
                'media_url' => null,
                'thumbnail_url' => null,
                'expires_at' => null,
                'expired' => true,
            ];
        }
        $expired = $ref->isExpired();

        return [
            'id' => $ref->id,
            'user_id' => $ref->user_id,
            'type' => $ref->type,
            'text' => $ref->text,
            'media_url' => $ref->media_url,
            'thumbnail_url' => $ref->thumbnail_url,
            'expires_at' => $ref->expires_at?->toIso8601String(),
            'expired' => $expired,
        ];
    }

    protected function referencedGroupPayload(): ?array
    {
        if (empty($this->message->referenced_group_message_id) || empty($this->message->referenced_group_id)) {
            return null;
        }
        $gm = $this->message->referencedGroupMessage;
        if (! $gm) {
            return [
                'group_id' => (int) $this->message->referenced_group_id,
                'group_message_id' => (int) $this->message->referenced_group_message_id,
                'group_name' => null,
                'body_preview' => null,
            ];
        }
        $g = $gm->relationLoaded('group') ? $gm->group : $gm->group()->first();

        return [
            'group_id' => (int) $this->message->referenced_group_id,
            'group_message_id' => (int) $this->message->referenced_group_message_id,
            'group_name' => $g->name ?? null,
            'body_preview' => mb_strimwidth((string) $gm->body, 0, 160, '…'),
        ];
    }

    protected function getMessageType(Message $m): ?string
    {
        if (!empty($m->type)) {
            return $m->type;
        }
        $loc = $m->location_data;
        if (is_array($loc)) {
            return !empty($loc['is_live']) ? 'live_location' : 'location';
        }
        if (!empty($m->contact_data)) {
            return 'contact';
        }
        if (!empty($m->call_data)) {
            return 'call';
        }
        return null;
    }

    /**
     * Poll data for broadcast when message type is poll (1:1 messages use message_id).
     */
    protected function getPollDataForMessage(?int $messageId, ?int $groupMessageId): ?array
    {
        if ($messageId === null && $groupMessageId === null) {
            return null;
        }
        $pollRow = $groupMessageId
            ? DB::table('message_polls')->where('group_message_id', $groupMessageId)->first()
            : DB::table('message_polls')->where('message_id', $messageId)->first();
        if (!$pollRow) {
            return null;
        }
        $options = DB::table('message_poll_options')
            ->where('poll_id', $pollRow->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($o) => ['id' => $o->id, 'text' => $o->text])
            ->values()
            ->all();
        return [
            'question' => $pollRow->question,
            'allow_multiple' => (bool) $pollRow->allow_multiple,
            'is_anonymous' => (bool) $pollRow->is_anonymous,
            'options' => $options,
        ];
    }

    protected function getAttachmentType($attachment): string
    {
        if (str_starts_with($attachment->mime_type, 'image/')) return 'image';
        if (str_starts_with($attachment->mime_type, 'video/')) return 'video';
        if ($attachment->mime_type === 'application/pdf') return 'pdf';
        return 'file';
    }

    public function broadcastWhen()
    {
        return !is_null($this->message->sender);
    }
}