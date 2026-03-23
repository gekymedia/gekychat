<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time like/comment cues for the post owner (TikTok-style floating UI on own posts).
 */
class WorldFeedPostEngagement implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $postId,
        public string $type,
        public array $actor,
        public ?string $commentPreview = null,
        public ?int $likesCount = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('world-feed-post.'.$this->postId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'world-feed.post.engagement';
    }

    public function broadcastWith(): array
    {
        return [
            'post_id' => $this->postId,
            'type' => $this->type,
            'actor' => $this->actor,
            'comment_preview' => $this->commentPreview,
            'likes_count' => $this->likesCount,
        ];
    }
}
