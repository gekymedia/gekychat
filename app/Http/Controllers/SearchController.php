<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class SearchController extends Controller
{
    /**
     * GET /api/v1/search?q=&limit=
     * Returns unified results for conversations and groups.
     * Shape: [{id, type, name, avatar_url, last_message, updated_at}]
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $q      = trim((string) $request->query('q', ''));
        $limit  = (int) $request->query('limit', 50);
        $limit  = max(1, min($limit, 100));

        // --- DMs (conversations the current user belongs to) ---
        $dmResults = $this->searchDmConversations($userId, $q, $limit);

        // --- Groups (only those the user belongs to) ---
        $groupResults = $this->searchGroups($userId, $q, $limit);

        // Merge, sort by updated_at desc, then slice to total limit
        $all = array_merge($dmResults, $groupResults);
        usort($all, function ($a, $b) {
            $ta = Carbon::parse($a['updated_at'] ?? '1970-01-01T00:00:00Z');
            $tb = Carbon::parse($b['updated_at'] ?? '1970-01-01T00:00:00Z');
            return $tb <=> $ta;
        });
        $all = array_slice($all, 0, $limit);

        return \App\Support\ApiResponse::data($all);
    }

    /**
     * Build DM search: conversations joined with "other user" + last message.
     */
    protected function searchDmConversations(int $userId, string $q, int $limit): array
    {
        if (!Schema::hasTable('conversations') || !Schema::hasTable('conversation_user')) {
            return [];
        }

        // Subquery: last message per conversation
        $lastDmSub = DB::table('messages')
            ->selectRaw('conversation_id, MAX(id) as last_id, MAX(created_at) as last_at')
            ->groupBy('conversation_id');

        $builder = DB::table('conversations as c')
            // current user's membership
            ->join('conversation_user as cu', function ($j) use ($userId) {
                $j->on('cu.conversation_id', '=', 'c.id')
                  ->where('cu.user_id', '=', $userId);
            })
            // the "other" participant (for 1:1 DMs)
            ->leftJoin('conversation_user as cu2', function ($j) use ($userId) {
                $j->on('cu2.conversation_id', '=', 'c.id')
                  ->where('cu2.user_id', '<>', $userId);
            })
            ->leftJoin('users as u', 'u.id', '=', 'cu2.user_id')
            // last message
            ->leftJoinSub($lastDmSub, 'lm', function ($j) {
                $j->on('lm.conversation_id', '=', 'c.id');
            })
            ->leftJoin('messages as m', 'm.id', '=', 'lm.last_id')
            ->selectRaw("
                c.id as conversation_id,
                COALESCE(NULLIF(TRIM(u.name), ''), u.phone, CONCAT('Chat #', c.id)) as partner_name,
                u.avatar as partner_avatar,
                m.body as last_body,
                COALESCE(m.created_at, c.updated_at, c.created_at) as last_at
            ")
            // basic LIKE search on partner name/phone and last message
            ->when($q !== '', function ($w) use ($q) {
                $like = '%' . str_replace(['%','_'], ['\\%','\\_'], $q) . '%';
                $w->where(function ($x) use ($like) {
                    $x->where('u.name', 'like', $like)
                      ->orWhere('u.phone', 'like', $like)
                      ->orWhere('m.body', 'like', $like);
                });
            })
            ->orderByRaw('COALESCE(m.created_at, c.updated_at, c.created_at) DESC')
            ->limit($limit);

        $rows = $builder->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'           => (int) $r->conversation_id,
                'type'         => 'conversation',
                'name'         => (string) $r->partner_name,
                'avatar_url'   => $r->partner_avatar ? (string) $r->partner_avatar : null,
                'last_message' => $r->last_body ? (string) $r->last_body : null,
                'updated_at'   => Carbon::parse($r->last_at ?? now())->toISOString(),
            ];
        }
        return $out;
    }

    /**
     * Build Group search: groups the user is in + last message.
     * Supports either "group_user" or "group_members" pivot table.
     */
    protected function searchGroups(int $userId, string $q, int $limit): array
    {
        if (!Schema::hasTable('groups')) {
            return [];
        }

        // Determine pivot name
        $pivot = Schema::hasTable('group_user') ? 'group_user' :
                 (Schema::hasTable('group_members') ? 'group_members' : null);

        if (!$pivot) {
            // No membership table → avoid leaking groups
            return [];
        }

        // Subquery: last message per group
        $lastGroupSub = DB::table('group_messages')
            ->selectRaw('group_id, MAX(id) as last_id, MAX(created_at) as last_at')
            ->groupBy('group_id');

        $builder = DB::table('groups as g')
            ->join($pivot . ' as gu', function ($j) use ($userId, $pivot) {
                // support either column naming
                $j->on('gu.group_id', '=', 'g.id');
                $j->where('gu.user_id', '=', $userId);
            })
            ->leftJoinSub($lastGroupSub, 'lm', function ($j) {
                $j->on('lm.group_id', '=', 'g.id');
            })
            ->leftJoin('group_messages as gm', 'gm.id', '=', 'lm.last_id')
            ->selectRaw("
                g.id as group_id,
                COALESCE(NULLIF(TRIM(g.name), ''), CONCAT('Group #', g.id)) as group_name,
                g.avatar as group_avatar,
                gm.body as last_body,
                COALESCE(gm.created_at, g.updated_at, g.created_at) as last_at
            ")
            ->when($q !== '', function ($w) use ($q) {
                $like = '%' . str_replace(['%','_'], ['\\%','\\_'], $q) . '%';
                $w->where(function ($x) use ($like) {
                    $x->where('g.name', 'like', $like)
                      ->orWhere('gm.body', 'like', $like);
                });
            })
            ->orderByRaw('COALESCE(gm.created_at, g.updated_at, g.created_at) DESC')
            ->limit($limit);

        $rows = Schema::hasTable('group_messages') ? $builder->get() : collect();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'           => (int) $r->group_id,
                'type'         => 'group',
                'name'         => (string) $r->group_name,
                'avatar_url'   => $r->group_avatar ? (string) $r->group_avatar : null,
                'last_message' => $r->last_body ? (string) $r->last_body : null,
                'updated_at'   => Carbon::parse($r->last_at ?? now())->toISOString(),
            ];
        }
        return $out;
    }
}
