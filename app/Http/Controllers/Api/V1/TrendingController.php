<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorldFeedPost;
use App\Models\WorldFeedLike;
use App\Models\WorldFeedComment;
use App\Models\WorldFeedView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Trending hashtags, challenges and sounds for the World Feed.
 */
class TrendingController extends Controller
{
    /**
     * GET /api/v1/trending/hashtags
     *
     * Returns the top hashtags sorted by a 48-hour engagement-weighted score.
     * Cached for 10 minutes to avoid hammering the DB on every feed load.
     */
    public function hashtags(Request $request)
    {
        $limit = min((int) $request->input('limit', 20), 50);

        $trending = Cache::remember("trending_hashtags_{$limit}", 600, function () use ($limit) {
            // JSON_TABLE / JSON unnesting differs across MySQL versions.
            // Use Eloquent pluck + PHP aggregation — fast enough for < 1 M posts.
            $since = now()->subHours(48);

            $posts = WorldFeedPost::where('is_public', true)
                ->where('created_at', '>=', $since)
                ->whereNotNull('tags')
                ->select('id', 'tags', 'likes_count', 'comments_count', 'views_count', 'shares_count')
                ->get();

            $scores = [];
            foreach ($posts as $post) {
                $tags = is_array($post->tags) ? $post->tags : json_decode($post->tags, true) ?? [];
                // Engagement-weighted score per post
                $postScore = ($post->likes_count ?? 0) * 3
                    + ($post->comments_count ?? 0) * 5
                    + ($post->shares_count ?? 0) * 8
                    + (($post->views_count ?? 0) / 100);

                foreach ($tags as $tag) {
                    $tag = ltrim(strtolower(trim((string) $tag)), '#');
                    if (strlen($tag) < 2) continue;
                    if (!isset($scores[$tag])) {
                        $scores[$tag] = ['tag' => '#' . $tag, 'score' => 0, 'post_count' => 0];
                    }
                    $scores[$tag]['score'] += $postScore;
                    $scores[$tag]['post_count']++;
                }
            }

            // Sort by score desc, take top N
            usort($scores, fn($a, $b) => $b['score'] <=> $a['score']);
            return array_values(array_slice($scores, 0, $limit));
        });

        return response()->json([
            'data' => $trending,
            'cached_until' => now()->addMinutes(10)->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/trending/sounds
     *
     * Returns the top audio tracks used in World Feed posts in the last 7 days.
     */
    public function sounds(Request $request)
    {
        $limit = min((int) $request->input('limit', 10), 30);

        $sounds = Cache::remember("trending_sounds_{$limit}", 900, function () use ($limit) {
            return DB::table('world_feed_audios as wfa')
                ->join('audio_libraries as al', 'al.id', '=', 'wfa.audio_library_id')
                ->join('world_feed_posts as wfp', 'wfp.id', '=', 'wfa.post_id')
                ->where('wfp.created_at', '>=', now()->subDays(7))
                ->where('wfp.is_public', true)
                ->select(
                    'al.id',
                    'al.title',
                    'al.artist',
                    'al.cover_url',
                    'al.preview_url',
                    DB::raw('COUNT(wfa.id) as use_count')
                )
                ->groupBy('al.id', 'al.title', 'al.artist', 'al.cover_url', 'al.preview_url')
                ->orderByDesc('use_count')
                ->limit($limit)
                ->get();
        });

        return response()->json(['data' => $sounds]);
    }

    /**
     * GET /api/v1/trending/creators
     *
     * Returns fast-rising creators based on follower growth in the last 24h.
     */
    public function creators(Request $request)
    {
        $limit = min((int) $request->input('limit', 10), 20);

        $creators = Cache::remember("trending_creators_{$limit}", 1800, function () use ($limit) {
            return DB::table('world_feed_follows as wff')
                ->join('users as u', 'u.id', '=', 'wff.creator_id')
                ->where('wff.created_at', '>=', now()->subHours(24))
                ->select(
                    'u.id',
                    'u.name',
                    'u.username',
                    'u.avatar_path',
                    DB::raw('COUNT(wff.id) as new_followers')
                )
                ->groupBy('u.id', 'u.name', 'u.username', 'u.avatar_path')
                ->orderByDesc('new_followers')
                ->limit($limit)
                ->get();
        });

        return response()->json(['data' => $creators]);
    }
}
