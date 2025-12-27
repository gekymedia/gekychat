<?php

namespace App\Events;

use App\Models\GroupMessage;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\View;

class GroupMessageSent implements ShouldBroadcastNow
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
        // Send group messages over a private channel so that Echo.private('group.{id}')
        // will receive the broadcast. Presence events are handled separately via
        // presence-group.{id} channels.
        return new \Illuminate\Broadcasting\PrivateChannel('group.' . $this->message->group_id);
    }

    public function broadcastWith()
    {
        // Don't include HTML - it exceeds Pusher's 10KB limit
        // Frontend will render the message from the data provided

        return [
            'message' => [
                'id' => $this->message->id,
                'body' => $this->message->body,
                'sender_id' => $this->message->sender_id,
                'group_id' => $this->message->group_id,
                'created_at' => $this->message->created_at->toISOString(),
                'reply_to' => $this->message->reply_to,
                'forwarded_from_id' => $this->message->forwarded_from_id,
            ],
            'id' => $this->message->id,
            'body' => $this->message->body,
            'sender_id' => $this->message->sender_id,
            'group_id' => $this->message->group_id,
            'created_at' => $this->message->created_at->toISOString(),
            'is_group' => true,
            'sender' => [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->name ?? $this->message->sender->phone,
                'avatar' => $this->message->sender->avatar_path ? \App\Helpers\UrlHelper::secureStorageUrl($this->message->sender->avatar_path) : null,
                'avatar_path' => $this->message->sender->avatar_path,
                'pivot' => $this->message->sender->pivot ?? null,
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
            'call_data' => $this->message->call_data ?? null, // Include call_data for call messages
            'location_data' => $this->message->location_data ?? null,
            'contact_data' => $this->message->contact_data ?? null,
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
        // Use the class name as the event identifier so that the frontend
        // can listen for ".GroupMessageSent" just like it does for
        // conversation events. See resources/js/chat/ChatCore.js.
        return 'GroupMessageSent';
    }

    public function broadcastWhen()
    {
        return !is_null($this->message->sender);
    }
}