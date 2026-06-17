<?php

namespace App\Events;

use App\Models\GroupMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserInboxGroupMessage implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public GroupMessage $message,
        public int $recipientId
    ) {
        $this->message->loadMissing(['sender', 'attachments', 'group']);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->recipientId);
    }

    public function broadcastAs(): string
    {
        return 'UserInboxGroupMessage';
    }

    public function broadcastWith(): array
    {
        $sender = $this->message->sender;
        $body = (string) ($this->message->body ?? '');
        $serverSentAtMs = (int) round(microtime(true) * 1000);
        if ($body === '' && $this->message->attachments->isNotEmpty()) {
            $body = '📎 Attachment';
        }

        return [
            'event_v' => 1,
            'ts_ms' => $serverSentAtMs,
            'server_sent_at_ms' => $serverSentAtMs,
            'event_type' => 'user.inbox.group_message',
            'type' => $this->getMessageType($this->message),
            'call_data' => $this->message->call_data ?? null,
            'location_data' => $this->message->location_data ?? null,
            'contact_data' => $this->message->contact_data ?? null,
            'message' => [
                'id' => $this->message->id,
                'group_id' => $this->message->group_id,
                'gid' => $this->message->group_id,
                'group_name' => $this->message->group?->name,
                'sender_id' => $this->message->sender_id,
                'sid' => $this->message->sender_id,
                'body' => $body,
                'created_at' => $this->message->created_at?->toISOString(),
                'is_group' => true,
                'type' => $this->getMessageType($this->message),
                'has_attachments' => $this->message->attachments->isNotEmpty(),
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

    protected function getMessageType(GroupMessage $m): ?string
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
