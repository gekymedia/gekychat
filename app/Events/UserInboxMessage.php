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
        } elseif ($body === '' && $this->message->attachments->isNotEmpty()) {
            $atts = $this->message->attachments;
            $isVoice = $atts->contains(function ($a) {
                $mime = (string) ($a->mime_type ?? '');
                return (bool) ($a->is_voicenote ?? false) || str_starts_with($mime, 'audio/');
            });
            $imageCount = $atts->filter(function ($a) {
                return str_starts_with((string) ($a->mime_type ?? ''), 'image/');
            })->count();
            $videoCount = $atts->filter(function ($a) {
                return str_starts_with((string) ($a->mime_type ?? ''), 'video/');
            })->count();

            if ($imageCount > 0 && $videoCount === 0 && ! $isVoice) {
                $body = $imageCount === 1 ? '📷 Photo' : "📷 {$imageCount} photos";
            } elseif ($videoCount > 0 && $imageCount === 0 && ! $isVoice) {
                $body = $videoCount === 1 ? '🎬 Video' : "🎬 {$videoCount} videos";
            } elseif ($isVoice && $imageCount === 0 && $videoCount === 0) {
                $body = '🎤 Voice message';
            } else {
                $body = '📎 Attachment';
            }
        }

        $attachments = $this->message->attachments->map(function ($attachment) {
            $mime = (string) ($attachment->mime_type ?? '');
            $isVoicenote = (bool) ($attachment->is_voicenote ?? false);
            $isAudio = $isVoicenote || str_starts_with($mime, 'audio/');
            return [
                'id' => $attachment->id,
                'url' => \App\Helpers\UrlHelper::secureStorageUrl($attachment->file_path),
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'type' => $isAudio
                    ? 'audio'
                    : (str_starts_with($mime, 'image/')
                        ? 'image'
                        : (str_starts_with($mime, 'video/') ? 'video' : 'file')),
                'is_image' => str_starts_with($mime, 'image/'),
                'is_video' => str_starts_with($mime, 'video/'),
                'is_audio' => $isAudio,
                'is_document' => ! str_starts_with($mime, 'image/')
                    && ! str_starts_with($mime, 'video/')
                    && ! $isAudio,
                'is_voicenote' => $isVoicenote || $isAudio,
                'shared_as_document' => (bool) ($attachment->shared_as_document ?? false),
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
            'type' => $this->getMessageType($this->message),
            'call_data' => $this->message->call_data ?? null,
            'location_data' => $this->message->location_data ?? null,
            'contact_data' => $this->message->contact_data ?? null,
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'cid' => $this->message->conversation_id,
                'sender_id' => $this->message->sender_id,
                'sid' => $this->message->sender_id,
                'body' => $body,
                'created_at' => $this->message->created_at?->toISOString(),
                'is_group' => false,
                'type' => $this->getMessageType($this->message),
                'has_attachments' => $this->message->attachments->isNotEmpty(),
                'reply_to_id' => $this->message->reply_to,
                'forwarded_from_id' => $this->message->forwarded_from_id,
                'referenced_status_id' => $this->message->referenced_status_id,
                'referenced_status' => $referencedStatus,
                'attachments' => $attachments,
                'call_data' => $this->message->call_data ?? null,
                'location_data' => $this->message->location_data ?? null,
                'contact_data' => $this->message->contact_data ?? null,
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

    protected function getMessageType(Message $m): ?string
    {
        if (! empty($m->type)) {
            return $m->type;
        }
        $loc = $m->location_data;
        if (is_array($loc)) {
            return ! empty($loc['is_live']) ? 'live_location' : 'location';
        }
        if (! empty($m->contact_data)) {
            return 'contact';
        }
        if (! empty($m->call_data)) {
            return 'call';
        }

        return null;
    }
}
