<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\WorldFeedFollow;
use App\Models\WorldFeedPost;
use App\Models\WorldFeedReengagementSend;
use App\Models\WorldFeedView;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * TikTok-style World feed nudge for users inactive ~24h+.
 */
class WorldFeedReengagementService
{
    public function __construct(
        private FcmService $fcm
    ) {
    }

    /**
     * @return array{candidates:int,sent:int,skipped:int}
     */
    public function run(int $inactiveHours = 24, int $limit = 500, int $cooldownHours = 48): array
    {
        $inactiveSince = now()->subHours(max(1, $inactiveHours));
        $notOlderThan = now()->subDays(30); // avoid forever-dormant accounts
        $cooldownSince = now()->subHours(max(24, $cooldownHours));
        $today = now()->toDateString();

        $recentlySentUserIds = WorldFeedReengagementSend::query()
            ->where('sent_at', '>=', $cooldownSince)
            ->pluck('user_id')
            ->all();

        $users = User::query()
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<=', $inactiveSince)
            ->where('last_seen_at', '>=', $notOlderThan)
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('device_tokens')
                    ->whereColumn('device_tokens.user_id', 'users.id')
                    ->whereNotNull('device_tokens.token')
                    ->where('device_tokens.token', '!=', '')
                    ->where('device_tokens.token', '!=', 'pending-fcm');
                if (\Illuminate\Support\Facades\Schema::hasColumn('device_tokens', 'is_active')) {
                    $q->where('device_tokens.is_active', true);
                }
            })
            ->when(!empty($recentlySentUserIds), fn ($q) => $q->whereNotIn('id', $recentlySentUserIds))
            ->with('notificationPreferences')
            ->orderBy('last_seen_at')
            ->limit($limit)
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($users as $user) {
            if (!$this->shouldNotifyUser($user)) {
                $skipped++;
                continue;
            }

            // Daily unique constraint safety
            if (WorldFeedReengagementSend::where('user_id', $user->id)->where('send_date', $today)->exists()) {
                $skipped++;
                continue;
            }

            $post = $this->pickPostForUser((int) $user->id);
            if (!$post) {
                $skipped++;
                continue;
            }

            if ($this->sendPush($user, $post)) {
                WorldFeedReengagementSend::create([
                    'user_id' => $user->id,
                    'post_id' => $post->id,
                    'sent_at' => now(),
                    'send_date' => $today,
                ]);
                $sent++;
            } else {
                $skipped++;
            }
        }

        return [
            'candidates' => $users->count(),
            'sent' => $sent,
            'skipped' => $skipped,
        ];
    }

    private function shouldNotifyUser(User $user): bool
    {
        $prefs = $user->notificationPreferences;
        if ($prefs instanceof NotificationPreference) {
            if ($prefs->isQuietHours()) {
                return false;
            }
            if ($prefs->push_world_reengagement === false) {
                return false;
            }
        }

        // Also honor legacy JSON settings from /notification-settings
        $userSettings = json_decode($user->settings ?? '{}', true);
        $jsonFlag = $userSettings['notifications']['world_reengagement_enabled'] ?? null;
        if ($jsonFlag === false) {
            return false;
        }

        return DeviceToken::getTokensForUser((int) $user->id) !== [];
    }

    private function pickPostForUser(int $userId): ?WorldFeedPost
    {
        $viewedIds = WorldFeedView::where('user_id', $userId)
            ->orderByDesc('viewed_at')
            ->limit(200)
            ->pluck('post_id')
            ->all();

        $followingIds = WorldFeedFollow::where('follower_id', $userId)
            ->pluck('creator_id')
            ->all();

        // Prefer recent posts from people they follow.
        if (!empty($followingIds)) {
            $fromFollowing = $this->basePostQuery()
                ->whereIn('creator_id', $followingIds)
                ->where('creator_id', '!=', $userId)
                ->when(!empty($viewedIds), fn ($q) => $q->whereNotIn('id', $viewedIds))
                ->orderByDesc('created_at')
                ->first();
            if ($fromFollowing) {
                return $fromFollowing;
            }
        }

        // Fallback: trending public posts from the last 7 days.
        return $this->basePostQuery()
            ->where('creator_id', '!=', $userId)
            ->when(!empty($viewedIds), fn ($q) => $q->whereNotIn('id', $viewedIds))
            ->orderByRaw('(likes_count + comments_count * 2 + views_count * 0.05) DESC')
            ->orderByDesc('created_at')
            ->first();
    }

    private function basePostQuery()
    {
        return WorldFeedPost::query()
            ->where('is_public', true)
            ->where('created_at', '>=', now()->subDays(7))
            ->with(['creator:id,name,username,avatar_path']);
    }

    private function sendPush(User $user, WorldFeedPost $post): bool
    {
        $creator = $post->creator;
        $authorName = $creator
            ? (string) ($creator->name ?: $creator->username ?: 'Someone')
            : 'Someone';

        $caption = trim((string) ($post->caption ?? ''));
        $snippet = $caption !== ''
            ? Str::limit($caption, 80)
            : 'See what’s new on World';

        $title = 'Discover on World';
        $body = $authorName . ': ' . $snippet;

        $thumb = (string) ($post->thumbnail_url ?? '');
        $shareCode = (string) ($post->share_code ?? '');
        $deepLink = $shareCode !== ''
            ? 'gekychat://world-feed/post/' . $shareCode
            : 'gekychat://world-feed/post/' . $post->id;

        $data = [
            'type' => 'world_reengagement',
            'post_id' => (string) $post->id,
            'share_code' => $shareCode,
            'thumbnail_url' => $thumb,
            'caption' => $snippet,
            'author_name' => $authorName,
            'author_id' => $creator ? (string) $creator->id : '',
            'title' => $title,
            'body' => $body,
            'deep_link' => $deepLink,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ];

        try {
            return $this->fcm->sendEngagementToUser(
                (int) $user->id,
                ['title' => $title, 'body' => $body, 'image' => $thumb],
                $data,
                'gekychat_world'
            );
        } catch (\Throwable $e) {
            Log::warning('WorldFeedReengagementService: FCM failed', [
                'user_id' => $user->id,
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
