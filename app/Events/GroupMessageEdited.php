<?php

namespace App\Events;

use App\Models\GroupMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageEdited implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $groupId;
    public int $messageId;
    public string $body;
    public ?string $edited_at;
    public int $editor_id;

    /**
     * Create a new event instance.
     */
    public function __construct(GroupMessage $message, ?int $editorId = null)
    {
        $this->groupId    = (int) $message->group_id;
        $this->messageId  = (int) $message->id;
        $this->body       = (string) $message->body;
        $this->edited_at  = optional($message->edited_at ?? $message->updated_at)->toISOString();
        $this->editor_id  = (int) ($editorId ?? $message->sender_id);
    }

    /**
     * The channel the event should broadcast on.
     */
    public function broadcastOn(): PrivateChannel
    {
        // Keep this name consistent with your Echo subscription in the group view:
        // window.Echo.private(`groups.${groupId}`)
        return new PrivateChannel("groups.{$this->groupId}");
    }

    /**
     * Optional custom event name on the client.
     */
    public function broadcastAs(): string
    {
        return 'GroupMessageEdited';
    }

    /**
     * Data sent to the client.
     */
    public function broadcastWith(): array
    {
        return [
            'group_id'   => $this->groupId,
            'message_id' => $this->messageId,
            'body'       => $this->body,
            'edited_at'  => $this->edited_at,
            'editor_id'  => $this->editor_id,
        ];
    }
}
