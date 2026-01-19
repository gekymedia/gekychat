<?php

namespace App\Events;

use App\Models\ChannelPost;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChannelPostCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $post;

    public function __construct(ChannelPost $post)
    {
        $this->post = $post->loadMissing(['poster', 'channel']);
    }

    public function broadcastOn(): PrivateChannel
    {
        // Broadcast to a channel-specific private channel
        // Followers will subscribe to this channel
        return new PrivateChannel('channel.' . $this->post->channel_id);
    }

    public function broadcastAs()
    {
        return 'ChannelPostCreated';
    }

    public function broadcastWith()
    {
        return [
            'post' => [
                'id' => $this->post->id,
                'channel_id' => $this->post->channel_id,
                'posted_by' => $this->post->posted_by,
                'type' => $this->post->type,
                'body' => $this->post->body,
                'media_url' => $this->post->media_url,
                'thumbnail_url' => $this->post->thumbnail_url,
                'created_at' => $this->post->created_at->toISOString(),
            ],
            'poster' => [
                'id' => $this->post->poster->id,
                'name' => $this->post->poster->name,
                'avatar_url' => $this->post->poster->avatar_url,
            ],
            'channel' => [
                'id' => $this->post->channel->id,
                'name' => $this->post->channel->name,
            ],
        ];
    }
}
