<?php

namespace App\Services;

use App\Models\WorldFeedActivity;
use App\Models\WorldFeedFollow;
use App\Models\LiveBroadcast;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Instagram/TikTok-style activity feed: record and optionally push (FCM) for world feed & live.
 */
class WorldFeedActivityService
{
    public function __construct(
        private FcmService $fcm
    ) {
    }

    /**
     * Record a single activity. Does not send push (call sendPushForActivity if needed).
     */
    public function record(
        int $recipientUserId,
        int $actorId,
        string $type,
        ?int $postId = null,
        ?int $commentId = null,
        ?int $broadcastId = null,
        ?string $summary = null
    ): WorldFeedActivity {
        $activity = WorldFeedActivity::create([
            'user_id' => $recipientUserId,
            'actor_id' => $actorId,
            'type' => $type,
            'post_id' => $postId,
            'comment_id' => $commentId,
            'broadcast_id' => $broadcastId,
            'summary' => $summary,
        ]);

        return $activity;
    }

    /**
     * Notify post owner when someone likes their post (do not notify self).
     */
    public function onPostLiked(int $postOwnerId, int $actorId, int $postId): ?WorldFeedActivity
    {
        if ($postOwnerId === $actorId) {
            return null;
        }
        return $this->record($postOwnerId, $actorId, 'post_like', $postId, null, null, 'liked your post');
    }

    /**
     * Notify post owner when someone comments (or notify parent comment author for replies). Do not notify self.
     */
    public function onCommentAdded(int $postOwnerId, int $parentCommentAuthorId, int $actorId, int $postId, int $commentId, bool $isReply): ?WorldFeedActivity
    {
        $recipient = $isReply && $parentCommentAuthorId ? $parentCommentAuthorId : $postOwnerId;
        if ($recipient === $actorId) {
            return null;
        }
        $type = $isReply ? 'comment_reply' : 'post_comment';
        $summary = $isReply ? 'replied to your comment' : 'commented on your post';
        return $this->record($recipient, $actorId, $type, $postId, $commentId, null, $summary);
    }

    /**
     * Notify creator when someone follows them (do not notify self).
     */
    public function onNewFollower(int $creatorId, int $actorId): ?WorldFeedActivity
    {
        if ($creatorId === $actorId) {
            return null;
        }
        return $this->record($creatorId, $actorId, 'new_follower', null, null, null, 'started following you');
    }

    /**
     * When a user goes live: create activity for each follower and optionally send FCM.
     */
    public function onLiveStarted(LiveBroadcast $broadcast): void
    {
        $broadcasterId = $broadcast->broadcaster_id;
        $broadcaster = User::find($broadcasterId);
        $name = $broadcaster ? ($broadcaster->name ?? $broadcaster->username ?? 'Someone') : 'Someone';

        $followerIds = WorldFeedFollow::where('creator_id', $broadcasterId)->pluck('follower_id');
        foreach ($followerIds as $followerId) {
            if ($followerId == $broadcasterId) {
                continue;
            }
            $this->record(
                $followerId,
                $broadcasterId,
                'live_started',
                null,
                null,
                $broadcast->id,
                'started a live'
            );
        }

        $this->sendLiveStartedPushToFollowers($broadcast, $followerIds->all(), $name);
    }

    /**
     * Send FCM to followers for "X started a live" (data-only so app can show local notification).
     */
    private function sendLiveStartedPushToFollowers(LiveBroadcast $broadcast, array $followerIds, string $broadcasterName): void
    {
        $data = [
            'type' => 'live_started',
            'broadcast_id' => (string) $broadcast->id,
            'broadcast_slug' => $broadcast->slug ?? '',
            'broadcaster_id' => (string) $broadcast->broadcaster_id,
            'title' => $broadcasterName . ' is live',
        ];
        $collapseKey = 'gekychat_world_live_' . $broadcast->id;
        foreach ($followerIds as $userId) {
            if ($userId == $broadcast->broadcaster_id) {
                continue;
            }
            try {
                $this->fcm->sendDataOnlyToUser((int) $userId, $data, $collapseKey);
            } catch (\Throwable $e) {
                Log::warning('WorldFeedActivityService: FCM live_started failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Optionally send FCM for a single activity (e.g. post_like, post_comment, new_follower).
     */
    public function sendPushForActivity(WorldFeedActivity $activity): bool
    {
        $actor = $activity->actor;
        $actorName = $actor ? ($actor->name ?? $actor->username ?? 'Someone') : 'Someone';
        $title = 'Activity';
        $body = $actorName . ' ' . ($activity->summary ?? '');
        $data = [
            'type' => 'world_activity',
            'activity_id' => (string) $activity->id,
            'post_id' => $activity->post_id ? (string) $activity->post_id : '',
            'broadcast_id' => $activity->broadcast_id ? (string) $activity->broadcast_id : '',
        ];
        return $this->fcm->sendToUser($activity->user_id, ['title' => $title, 'body' => $body], $data);
    }
}
