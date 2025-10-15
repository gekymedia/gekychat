<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class MessageEdited implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $isGroup;
    public $editorId;

    /**
     * Create a new event instance.
     *
     * @param mixed $message
     * @param bool $isGroup
     * @param int $editorId
     */
    public function __construct($message, $isGroup = false, $editorId = null)
    {
        $this->message = $message;
        $this->isGroup = $isGroup;
        $this->editorId = $editorId ?? $message->sender_id;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return $this->isGroup
            ? new PrivateChannel('group.' . $this->message->group_id)
            : new PrivateChannel('chat.' . $this->message->conversation_id);
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        $baseData = [
            'id' => $this->message->id,
            'body' => $this->message->body,
            'edited_at' => $this->message->edited_at->toDateTimeString(),
            'editor_id' => $this->editorId,
            'is_group' => $this->isGroup,
            'attachments' => $this->message->attachments->map(function($attachment) {
                return [
                    'id' => $attachment->id,
                    'url' => asset('storage/' . $attachment->file_path),
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                ];
            }),
        ];

        if ($this->isGroup) {
            $baseData['group_id'] = $this->message->group_id;
            $baseData['sender'] = [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->name,
            ];
        } else {
            $baseData['conversation_id'] = $this->message->conversation_id;
        }

        return $baseData;
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'message.edited';
    }

    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function broadcastWhen()
    {
        return $this->message->edited_at !== null;
    }
}