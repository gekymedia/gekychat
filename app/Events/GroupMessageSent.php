<?php

namespace App\Events;

use App\Models\GroupMessage;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\View;

class GroupMessageSent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $message;
    public $isGroup = true;

    public function __construct(GroupMessage $message)
    {
        $this->message = $message->loadMissing(['sender', 'attachments', 'replyTo.sender', 'forwardedFrom.sender', 'group']);
    }

    public function broadcastOn()
    {
        // ✅ FIXED: Use PresenceChannel for groups
        return new PresenceChannel('group.' . $this->message->group_id);
    }

    public function broadcastWith()
    {
        // Generate HTML for the message
        $html = View::make('chat.shared.message', [
            'message' => $this->message,
            'isGroup' => true,
            'group' => $this->message->group
        ])->render();

        return [
            'id' => $this->message->id,
            'body' => $this->message->body,
            'sender_id' => $this->message->sender_id,
            'group_id' => $this->message->group_id,
            'created_at' => $this->message->created_at->toISOString(),
            'is_group' => true,
            'html' => $html,
            'sender' => [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->name ?? $this->message->sender->phone,
                'avatar' => $this->message->sender->avatar_url,
            ],
            'attachments' => $this->message->attachments->map(function($attachment) {
                return [
                    'id' => $attachment->id,
                    'url' => \Illuminate\Support\Facades\Storage::url($attachment->file_path),
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'type' => $this->getAttachmentType($attachment),
                ];
            }),
            'reply_to' => $this->message->replyTo ? [
                'id' => $this->message->replyTo->id,
                'body' => $this->message->replyTo->body,
                'sender' => [
                    'id' => $this->message->replyTo->sender->id,
                    'name' => $this->message->replyTo->sender->name ?? $this->message->replyTo->sender->phone,
                ]
            ] : null,
            'forwarded_from' => $this->message->forwardedFrom ? [
                'id' => $this->message->forwardedFrom->id,
                'body' => $this->message->forwardedFrom->body,
                'sender' => [
                    'id' => $this->message->forwardedFrom->sender->id,
                    'name' => $this->message->forwardedFrom->sender->name ?? $this->message->forwardedFrom->sender->phone,
                ]
            ] : null,
        ];
    }

    protected function getAttachmentType($attachment): string
    {
        if (str_starts_with($attachment->mime_type, 'image/')) return 'image';
        if (str_starts_with($attachment->mime_type, 'video/')) return 'video';
        if ($attachment->mime_type === 'application/pdf') return 'pdf';
        return 'file';
    }

    public function broadcastAs()
    {
        return 'message.sent'; // ✅ Consistent with MessageSent event
    }

    public function broadcastWhen()
    {
        return !is_null($this->message->sender);
    }
}