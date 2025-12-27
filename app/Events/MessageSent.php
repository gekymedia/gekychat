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

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $isGroup = false;

    public function __construct(Message $message)
    {
        $this->message = $message->loadMissing(['sender', 'attachments', 'replyTo.sender', 'forwardedFrom.sender']);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversation.' . $this->message->conversation_id);
    }

    // ✅ ADDED: This creates the ".MessageSent" event that Echo listens for
    public function broadcastAs()
    {
        return 'MessageSent';
    }

    public function broadcastWith()
    {
        // Don't include HTML - it exceeds Pusher's 10KB limit
        // Frontend will render the message from the data provided
        
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
            'message' => [
                'id' => $this->message->id,
                'body' => $bodyPlain,
                'sender_id' => $this->message->sender_id,
                'conversation_id' => $this->message->conversation_id,
                'created_at' => $this->message->created_at->toISOString(),
                'is_encrypted' => $this->message->is_encrypted,
                'reply_to' => $this->message->reply_to,
                'forwarded_from_id' => $this->message->forwarded_from_id,
            ],
            'id' => $this->message->id,
            'body' => $bodyPlain,
            'sender_id' => $this->message->sender_id,
            'conversation_id' => $this->message->conversation_id,
            'created_at' => $this->message->created_at->toISOString(),
            'is_group' => false,
            'is_encrypted' => $this->message->is_encrypted,
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
            'metadata' => $this->message->metadata ?? null, // Include metadata for group references, etc.
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