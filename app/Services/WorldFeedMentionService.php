<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorldFeedPost;
use App\Models\WorldFeedPostMention;
use Illuminate\Support\Facades\Log;

class WorldFeedMentionService
{
    public function __construct(
        private MentionService $mentionService,
        private WorldFeedActivityService $activityService
    ) {
    }

    /**
     * Parse @usernames in caption, persist mentions, notify newly tagged users.
     */
    public function syncMentionsForPost(WorldFeedPost $post, int $authorId): int
    {
        $caption = (string) ($post->caption ?? '');
        $parsed = $this->mentionService->parseMentions($caption);

        if (empty($parsed)) {
            WorldFeedPostMention::where('post_id', $post->id)->delete();

            return 0;
        }

        $users = $this->mentionService->resolveMentions($parsed, null);
        $targetUserIds = $users
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $id !== $authorId)
            ->unique()
            ->values();

        WorldFeedPostMention::where('post_id', $post->id)
            ->whereNotIn('mentioned_user_id', $targetUserIds->all())
            ->delete();

        $notified = 0;
        foreach ($targetUserIds as $userId) {
            $created = WorldFeedPostMention::firstOrCreate(
                [
                    'post_id' => $post->id,
                    'mentioned_user_id' => $userId,
                ],
                [
                    'mentioned_by_user_id' => $authorId,
                ]
            );

            if (!$created->wasRecentlyCreated) {
                continue;
            }

            try {
                $activity = $this->activityService->onPostMentioned($userId, $authorId, (int) $post->id);
                if ($activity) {
                    $notified++;
                }
            } catch (\Throwable $e) {
                Log::warning('WorldFeedMentionService: notify failed', [
                    'post_id' => $post->id,
                    'mentioned_user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $notified;
    }
}
