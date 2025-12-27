<?php

namespace App\Events;

use App\Models\Status;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StatusCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $status;

    public function __construct(Status $status)
    {
        $this->status = $status->load('user');
    }

    public function broadcastOn()
    {
        return new Channel('status.updates');
    }

    public function broadcastAs()
    {
        return 'status.created';
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->status->id,
            'user_id' => $this->status->user_id,
            'type' => $this->status->type,
            'text' => $this->status->text ?? '',
            'content' => $this->status->text ?? '',
            'media_url' => $this->status->media_url,
            'background_color' => $this->status->background_color,
            'text_color' => $this->status->text_color,
            'font_size' => $this->status->font_size,
            'duration' => $this->status->duration,
            'expires_at' => $this->status->expires_at ? $this->status->expires_at->toISOString() : null,
            'created_at' => $this->status->created_at ? $this->status->created_at->toISOString() : now()->toISOString(),
            'view_count' => $this->status->view_count ?? 0,
            'user' => [
                'id' => $this->status->user->id,
                'name' => $this->status->user->name,
                'phone' => $this->status->user->phone,
                'avatar_path' => $this->status->user->avatar_path,
                'initial' => $this->status->user->initial ?? strtoupper(substr($this->status->user->name ?? 'U', 0, 1)),
            ]
        ];
    }
}
