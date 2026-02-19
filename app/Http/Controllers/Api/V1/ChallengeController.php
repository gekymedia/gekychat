<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ChallengeController extends Controller
{
    // ── Browse ───────────────────────────────────────────────────────────────

    /** GET /api/v1/challenges — paginated list of active challenges */
    public function index(Request $request)
    {
        $r      = $request->validate(['per_page' => 'nullable|integer|min:1|max:50']);
        $perPage = (int) ($r['per_page'] ?? 20);
        $userId  = $request->user()->id;

        $challenges = DB::table('challenges')
            ->where('status', 'active')
            ->orderByDesc('participants_count')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $ids = collect($challenges->items())->pluck('id')->all();
        $joined = DB::table('challenge_participations')
            ->where('user_id', $userId)
            ->whereIn('challenge_id', $ids)
            ->pluck('challenge_id')
            ->flip();

        $items = collect($challenges->items())->map(fn ($c) => [
            'id'                 => $c->id,
            'title'              => $c->title,
            'hashtag'            => $c->hashtag,
            'description'        => $c->description,
            'cover_url'          => $c->cover_url,
            'audio_url'          => $c->audio_url,
            'participants_count' => $c->participants_count,
            'posts_count'        => $c->posts_count,
            'views_count'        => $c->views_count,
            'ends_at'            => $c->ends_at,
            'is_joined'          => isset($joined[$c->id]),
        ]);

        return response()->json([
            'data'       => $items,
            'total'      => $challenges->total(),
            'next_page'  => $challenges->hasMorePages() ? $challenges->currentPage() + 1 : null,
        ]);
    }

    /** GET /api/v1/challenges/{id} — single challenge detail */
    public function show(Request $request, int $id)
    {
        $challenge = DB::table('challenges')->where('id', $id)->first();
        if (!$challenge) return response()->json(['error' => 'Not found'], 404);

        DB::table('challenges')->where('id', $id)->increment('views_count');

        $userId   = $request->user()->id;
        $isJoined = DB::table('challenge_participations')
            ->where('challenge_id', $id)->where('user_id', $userId)->exists();

        // Recent posts in this challenge
        $posts = DB::table('world_posts')
            ->where('challenge_id', $id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'id'                 => $challenge->id,
            'title'              => $challenge->title,
            'hashtag'            => $challenge->hashtag,
            'description'        => $challenge->description,
            'cover_url'          => $challenge->cover_url,
            'audio_url'          => $challenge->audio_url,
            'participants_count' => $challenge->participants_count,
            'posts_count'        => $challenge->posts_count,
            'views_count'        => $challenge->views_count,
            'ends_at'            => $challenge->ends_at,
            'is_joined'          => $isJoined,
            'recent_posts'       => $posts,
        ]);
    }

    // ── Create ───────────────────────────────────────────────────────────────

    /** POST /api/v1/challenges — create a new challenge */
    public function store(Request $request)
    {
        $r = $request->validate([
            'title'       => 'required|string|max:120',
            'hashtag'     => 'required|string|max:60|unique:challenges,hashtag',
            'description' => 'nullable|string|max:500',
            'ends_at'     => 'nullable|date|after:now',
        ]);

        // Normalize hashtag: lowercase, strip leading #, add it back
        $tag = '#' . ltrim(strtolower($r['hashtag']), '#');
        $r['hashtag'] = $tag;

        $id = DB::table('challenges')->insertGetId([
            'creator_id'  => $request->user()->id,
            'title'       => $r['title'],
            'hashtag'     => $tag,
            'description' => $r['description'] ?? null,
            'status'      => 'active',
            'starts_at'   => now(),
            'ends_at'     => $r['ends_at'] ?? null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json(DB::table('challenges')->find($id), 201);
    }

    // ── Join / Leave ─────────────────────────────────────────────────────────

    /** POST /api/v1/challenges/{id}/join */
    public function join(Request $request, int $id)
    {
        $challenge = DB::table('challenges')->where('id', $id)->where('status', 'active')->first();
        if (!$challenge) return response()->json(['error' => 'Challenge not found or ended'], 404);

        $userId = $request->user()->id;
        $exists = DB::table('challenge_participations')
            ->where('challenge_id', $id)->where('user_id', $userId)->exists();

        if (!$exists) {
            DB::table('challenge_participations')->insert([
                'challenge_id' => $id,
                'user_id'      => $userId,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            DB::table('challenges')->where('id', $id)->increment('participants_count');
        }

        return response()->json(['joined' => true, 'participants_count' => $challenge->participants_count + 1]);
    }

    /** DELETE /api/v1/challenges/{id}/join */
    public function leave(Request $request, int $id)
    {
        $userId = $request->user()->id;
        $deleted = DB::table('challenge_participations')
            ->where('challenge_id', $id)->where('user_id', $userId)->delete();

        if ($deleted) {
            DB::table('challenges')->where('id', $id)->decrement('participants_count');
        }
        return response()->json(['joined' => false]);
    }

    // ── Trending ─────────────────────────────────────────────────────────────

    /** GET /api/v1/challenges/trending */
    public function trending()
    {
        $data = Cache::remember('challenges_trending', 300, function () {
            return DB::table('challenges')
                ->where('status', 'active')
                ->orderByRaw('(participants_count * 2 + posts_count) DESC')
                ->limit(10)
                ->get(['id', 'title', 'hashtag', 'cover_url', 'participants_count', 'posts_count']);
        });
        return response()->json($data);
    }
}
