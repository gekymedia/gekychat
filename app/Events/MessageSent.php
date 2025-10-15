<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;


class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $isGroup;

    public function __construct($message, bool $isGroup = false)
    {
        $this->message = $message->loadMissing(['sender', 'attachments', 'replyTo.sender']);
        $this->isGroup = $isGroup;
    }

    public function broadcastOn()
    {
        return $this->isGroup
            ? new PrivateChannel('group.'.$this->message->group_id)
            : new PrivateChannel('chat.'.$this->message->conversation_id);
    }

    public function broadcastWith()
    {
        $baseData = [
            'id' => $this->message->id,
            'body' => $this->message->is_encrypted 
                ? '[Encrypted Message]' 
                : $this->message->body,
            'sender_id' => $this->message->sender_id,
            'created_at' => $this->message->created_at->toISOString(),
            'is_encrypted' => $this->message->is_encrypted,
            'is_group' => $this->isGroup,
            'attachments' => $this->message->attachments->map(function($attachment) {
                return [
                    'id' => $attachment->id,
                    'url' => Storage::url($attachment->file_path),
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'type' => $this->getAttachmentType($attachment),
                ];
            }),
        ];

        if ($this->isGroup) {
            $baseData['group_id'] = $this->message->group_id;
            $baseData['sender'] = $this->getSenderData();
        } else {
            $baseData['conversation_id'] = $this->message->conversation_id;
        }

        if ($this->message->reply_to_id) {
            $baseData['reply_to'] = [
                'id' => $this->message->replyTo->id,
                'body' => Str::limit($this->message->replyTo->body, 100),
                'sender_name' => $this->message->replyTo->sender->name ?? 'Unknown',
            ];
        }

        return $baseData;
    }

    protected function getSenderData(): array
    {
        return [
            'id' => $this->message->sender->id,
            'name' => $this->message->sender->name,
            'phone' => $this->message->sender->phone,
            'avatar' => $this->message->sender->avatar_url,
        ];
    }

    protected function getAttachmentType($attachment): string
    {
        if (Str::startsWith($attachment->mime_type, 'image/')) return 'image';
        if (Str::startsWith($attachment->mime_type, 'video/')) return 'video';
        if ($attachment->mime_type === 'application/pdf') return 'pdf';
        return 'file';
    }

    public function broadcastAs()
    {
        return 'message.sent';
    }

    public function broadcastWhen()
    {
        return !is_null($this->message->sender);
    }
}