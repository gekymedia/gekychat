<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MeilisearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Full-text message search endpoint.
 *
 * GET /api/v1/search/messages?q=hello&conversation_id=5&limit=30
 *
 * Strategy:
 *   1. If Meilisearch is healthy → return Meilisearch results.
 *   2. Otherwise fall back to MySQL FULLTEXT search (if available).
 *   3. Last resort: LIKE '%query%' (always works, slower).
 */
class SearchMessagesController extends Controller
{
    public function __construct(private MeilisearchService $meili) {}

    public function search(Request $request)
    {
        $r = $request->validate([
            'q'               => 'required|string|min:1|max:255',
            'conversation_id' => 'nullable|integer',
            'group_id'        => 'nullable|integer',
            'limit'           => 'nullable|integer|min:1|max:100',
        ]);

        $query          = trim($r['q']);
        $userId         = $request->user()->id;
        $conversationId = isset($r['conversation_id']) ? (int) $r['conversation_id'] : null;
        $groupId        = isset($r['group_id'])        ? (int) $r['group_id']        : null;
        $limit          = (int) ($r['limit'] ?? 30);

        // ── Try Meilisearch first ────────────────────────────────────────────
        if ($this->meili->isHealthy()) {
            $result = $this->meili->searchMessages($query, $userId, $conversationId, $groupId, $limit);
            if ($result['from'] === 'meilisearch' && $result['total'] > 0) {
                return response()->json($result);
            }
        }

        // ── SQL fallback ────────────────────────────────────────────────────
        return response()->json($this->sqlSearch($query, $userId, $conversationId, $groupId, $limit));
    }

    // ── SQL fallback (FULLTEXT → LIKE) ──────────────────────────────────────

    private function sqlSearch(string $q, int $userId, ?int $convId, ?int $groupId, int $limit): array
    {
        $base = DB::table('messages')
            ->select('id', 'body', 'sender_id', 'created_at',
                     'conversation_id', 'group_id')
            ->where('is_deleted', false)
            ->where(function ($where) use ($userId) {
                // Only messages in conversations/groups the user belongs to
                $where->whereIn('conversation_id', function ($sub) use ($userId) {
                    $sub->select('id')->from('conversations')
                        ->where(function ($c) use ($userId) {
                            $c->where('user_one', $userId)->orWhere('user_two', $userId);
                        });
                })->orWhereIn('group_id', function ($sub) use ($userId) {
                    $sub->select('group_id')->from('group_members')
                        ->where('user_id', $userId);
                });
            });

        if ($convId) {
            $base->where('conversation_id', $convId);
        } elseif ($groupId) {
            $base->where('group_id', $groupId);
        }

        // Prefer FULLTEXT (MySQL) if available; else LIKE
        $hasFullText = $this->hasFullTextIndex();
        if ($hasFullText) {
            $rows = (clone $base)
                ->whereRaw('MATCH(body) AGAINST(? IN BOOLEAN MODE)', ["+{$q}*"])
                ->orderByRaw('MATCH(body) AGAINST(? IN BOOLEAN MODE) DESC', ["+{$q}*"])
                ->limit($limit)
                ->get();
        } else {
            $rows = (clone $base)
                ->where('body', 'like', "%{$q}%")
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        }

        // Add simple highlight (wrap exact match with <mark>)
        $hits = $rows->map(function ($row) use ($q) {
            $row->highlighted_body = str_ireplace(
                $q,
                "<mark>{$q}</mark>",
                $row->body
            );
            return $row;
        })->values()->toArray();

        return [
            'hits'  => $hits,
            'total' => count($hits),
            'from'  => $hasFullText ? 'mysql_fulltext' : 'mysql_like',
        ];
    }

    private function hasFullTextIndex(): bool
    {
        try {
            $result = DB::select("SHOW INDEX FROM messages WHERE Key_name = 'messages_body_fulltext'");
            return !empty($result);
        } catch (\Throwable) {
            return false;
        }
    }
}
