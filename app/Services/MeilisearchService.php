<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Lightweight Meilisearch integration using the REST API directly.
 * Falls back gracefully when Meilisearch is not configured or unreachable.
 *
 * Config keys (set in .env):
 *   MEILISEARCH_HOST=http://localhost:7700
 *   MEILISEARCH_KEY=masterKey
 */
class MeilisearchService
{
    private string $host;
    private string $key;
    private bool   $enabled;

    public function __construct()
    {
        $this->host    = rtrim(config('services.meilisearch.host', ''), '/');
        $this->key     = config('services.meilisearch.key', '');
        $this->enabled = !empty($this->host);
    }

    // ── Index management ────────────────────────────────────────────────────

    /** Ensure the messages index exists with correct filterable/sortable fields. */
    public function ensureMessagesIndex(): void
    {
        if (!$this->enabled) return;
        try {
            $this->http()->post('/indexes', ['uid' => 'messages', 'primaryKey' => 'id']);
            $this->http()->patch('/indexes/messages/settings', [
                'filterableAttributes' => ['conversation_id', 'group_id', 'sender_id', 'is_deleted'],
                'sortableAttributes'   => ['created_at'],
                'rankingRules'         => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('MeilisearchService: ensureMessagesIndex failed: ' . $e->getMessage());
        }
    }

    // ── Document operations ─────────────────────────────────────────────────

    /**
     * Index or update a single message.
     * @param array $message Associative array of message fields.
     */
    public function indexMessage(array $message): void
    {
        if (!$this->enabled) return;
        try {
            $doc = $this->prepareDoc($message);
            $this->http()->post('/indexes/messages/documents', [$doc]);
        } catch (\Throwable $e) {
            Log::warning('MeilisearchService: indexMessage failed: ' . $e->getMessage());
        }
    }

    /**
     * Bulk-index an array of messages (used for initial import / re-index).
     * @param array $messages Array of associative arrays.
     */
    public function indexMessages(array $messages): void
    {
        if (!$this->enabled || empty($messages)) return;
        try {
            $docs = array_map([$this, 'prepareDoc'], $messages);
            // Meilisearch recommends batches ≤ 10 000 docs
            foreach (array_chunk($docs, 1000) as $chunk) {
                $this->http()->post('/indexes/messages/documents', $chunk);
            }
        } catch (\Throwable $e) {
            Log::warning('MeilisearchService: indexMessages failed: ' . $e->getMessage());
        }
    }

    /** Remove a message from the index (e.g. after soft-delete). */
    public function deleteMessage(int $messageId): void
    {
        if (!$this->enabled) return;
        try {
            $this->http()->delete("/indexes/messages/documents/{$messageId}");
        } catch (\Throwable $e) {
            Log::warning('MeilisearchService: deleteMessage failed: ' . $e->getMessage());
        }
    }

    // ── Search ──────────────────────────────────────────────────────────────

    /**
     * Full-text search messages.
     *
     * @param  string   $query           User search query.
     * @param  int      $userId          Authenticated user ID (for access filtering).
     * @param  int|null $conversationId  Restrict to a conversation.
     * @param  int|null $groupId         Restrict to a group.
     * @param  int      $limit           Max results.
     * @return array    ['hits' => [...], 'total' => int, 'from' => 'meilisearch']
     */
    public function searchMessages(
        string $query,
        int    $userId,
        ?int   $conversationId = null,
        ?int   $groupId        = null,
        int    $limit          = 30
    ): array {
        if (!$this->enabled || trim($query) === '') {
            return ['hits' => [], 'total' => 0, 'from' => 'empty'];
        }

        $filters = ['is_deleted = false'];
        if ($conversationId !== null) {
            $filters[] = "conversation_id = {$conversationId}";
        } elseif ($groupId !== null) {
            $filters[] = "group_id = {$groupId}";
        }

        $payload = [
            'q'                    => $query,
            'limit'                => $limit,
            'filter'               => implode(' AND ', $filters),
            'attributesToRetrieve' => ['id', 'body', 'sender_id', 'sender_name',
                                       'conversation_id', 'group_id', 'created_at'],
            'attributesToHighlight' => ['body'],
            'highlightPreTag'       => '<mark>',
            'highlightPostTag'      => '</mark>',
            'showMatchesPosition'   => true,
        ];

        try {
            $resp = $this->http()->post('/indexes/messages/search', $payload);
            if (!$resp->successful()) {
                return ['hits' => [], 'total' => 0, 'from' => 'error'];
            }
            $data = $resp->json();
            return [
                'hits'  => $data['hits']        ?? [],
                'total' => $data['estimatedTotalHits'] ?? count($data['hits'] ?? []),
                'from'  => 'meilisearch',
            ];
        } catch (\Throwable $e) {
            Log::warning('MeilisearchService: search failed: ' . $e->getMessage());
            return ['hits' => [], 'total' => 0, 'from' => 'error'];
        }
    }

    /** TRUE when Meilisearch is reachable (cached 30 s). */
    public function isHealthy(): bool
    {
        if (!$this->enabled) return false;
        return Cache::remember('meilisearch_healthy', 30, function () {
            try {
                return $this->http()->get('/health')->successful();
            } catch (\Throwable) {
                return false;
            }
        });
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function prepareDoc(array $msg): array
    {
        return [
            'id'              => (int) ($msg['id'] ?? 0),
            'body'            => $msg['body'] ?? '',
            'sender_id'       => (int) ($msg['sender_id'] ?? 0),
            'sender_name'     => $msg['sender_name'] ?? '',
            'conversation_id' => isset($msg['conversation_id']) ? (int) $msg['conversation_id'] : null,
            'group_id'        => isset($msg['group_id'])        ? (int) $msg['group_id']        : null,
            'is_deleted'      => (bool) ($msg['is_deleted'] ?? false),
            'created_at'      => isset($msg['created_at']) ? strtotime($msg['created_at']) : time(),
        ];
    }

    private function http()
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->key}",
            'Content-Type'  => 'application/json',
        ])->baseUrl($this->host)->timeout(5);
    }
}
