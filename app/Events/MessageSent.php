<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Crypt;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $message;
    public $isGroup = false;

    public function __construct(Message $message)
    {
        $this->message = $message->loadMissing(['sender', 'attachments', 'replyTo.sender', 'forwardedFrom.sender']);
    }

    // ✅ Single, correct channel
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.' . $this->message->conversation_id);
    }

    public function broadcastWith()
    {
        $html = View::make('chat.shared.message', [
            'message' => $this->message,
            'isGroup' => false,
            'group' => null
        ])->render();

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

        return [
            'id' => $this->message->id,
            'body' => $this->message->body,
            'body_plain' => $bodyPlain,
            'sender_id' => $this->message->sender_id,
            'conversation_id' => $this->message->conversation_id,
            'created_at' => $this->message->created_at->toISOString(),
            'is_group' => false,
            'is_encrypted' => $this->message->is_encrypted,
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

    // ❌ Remove this to use default event name "MessageSent"
    // public function broadcastAs() { return 'message.sent'; }

    public function broadcastWhen()
    {
        return !is_null($this->message->sender);
    }
}
