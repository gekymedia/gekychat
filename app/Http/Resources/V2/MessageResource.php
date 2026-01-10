<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'content' => $this->body,
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'avatar' => $this->sender->avatar_url,
            ],
            'reply_to' => $this->when($this->replyTo, [
                'id' => $this->replyTo?->id,
                'content' => $this->replyTo?->body,
                'sender_name' => $this->replyTo?->sender->name,
            ]),
            'reactions' => $this->reactions->map(fn($r) => [
                'emoji' => $r->emoji,
                'user_id' => $r->user_id,
                'user_name' => $r->user->name,
            ]),
            'timestamp' => $this->created_at->timestamp,
            'edited_at' => $this->edited_at?->timestamp,
            'is_edited' => !is_null($this->edited_at),
        ];
    }
}
