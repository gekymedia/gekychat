<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

/**
 * ✅ MODERN: Sync API for delta/incremental sync
 * GET /sync/delta, GET /sync/changes, GET /conversations/updated-since
 */
class SyncController extends Controller
{
    /**
     * Delta sync: messages for a conversation after since_message_id
     * GET /api/v1/sync/delta?conversation_id=1&since_message_id=123&limit=100
     */
    public function delta(Request $r)
    {
        $r->validate([
            'conversation_id' => 'required|integer|exists:conversations,id',
            'since_message_id' => 'required|integer',
            'limit' => 'nullable|integer|min:1|max:500',
        ]);
        $conv = Conversation::findOrFail($r->conversation_id);
        if (!$conv->isParticipant($r->user()->id)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $limit = min($r->input('limit', 100), 500);
        $messages = Message::where('conversation_id', $r->conversation_id)
            ->where('id', '>', $r->since_message_id)
            ->with(['sender:id,name,phone,username,avatar_path', 'attachments:id,attachable_id,attachable_type,file_path,original_name,mime_type,size', 'replyTo:id,body,sender_id', 'reactions.user:id,name,avatar_path'])
            ->orderBy('id')
            ->limit($limit)
            ->get();
        return response()->json([
            'messages' => MessageResource::collection($messages),
            'has_more' => $messages->count() === $limit,
            'next_since_id' => $messages->isNotEmpty() ? $messages->last()->id : $r->since_message_id,
        ]);
    }

    /**
     * Changes since timestamp: conversations updated + new message counts
     * GET /api/v1/sync/changes?since=2026-02-19T00:00:00Z
     */
    public function changes(Request $r)
    {
        $r->validate([
            'since' => 'required|date',
        ]);
        $user = $r->user();
        $since = \Carbon\Carbon::parse($r->since);
        $convIds = $user->conversations()->pluck('id');
        $updated = Conversation::whereIn('id', $convIds)
            ->where('updated_at', '>=', $since)
            ->select('id', 'updated_at', 'user_one_id', 'user_two_id', 'is_group')
            ->get()
            ->map(fn($c) => ['id' => $c->id, 'updated_at' => $c->updated_at->toIso8601String()]);
        $newMessagesCount = Message::whereIn('conversation_id', $convIds)
            ->where('created_at', '>=', $since)
            ->where('sender_id', '!=', $user->id)
            ->selectRaw('conversation_id, count(*) as cnt')
            ->groupBy('conversation_id')
            ->pluck('cnt', 'conversation_id');
        return response()->json([
            'conversations_updated' => $updated,
            'new_message_counts' => $newMessagesCount,
            'since' => $since->toIso8601String(),
        ]);
    }
}
