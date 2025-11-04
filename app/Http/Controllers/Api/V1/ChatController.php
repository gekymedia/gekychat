<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Group;
use Illuminate\Http\Request;
use App\Models\MessageStatus;

class ChatController extends Controller
{
    /**
     * GET /api/v1/chats
     * Unified inbox: DMs + Groups
     */
  public function index(Request $r)
{
    $u = $r->user()->id;

    // DMs
    $dmThreads = Conversation::query()
        ->where(fn ($q) => $q->where('user_one_id', $u)->orWhere('user_two_id', $u))
        ->with(['userOne:id,name,phone,avatar_path', 'userTwo:id,name,phone,avatar_path'])
        ->get()
        ->map(function ($c) use ($u) {
            $other = $c->user_one_id === $u ? $c->userTwo : $c->userOne;

            $last = $c->messages()
                ->notExpired()
                ->visibleTo($u)
                ->latest()
                ->first();

            // ✅ unread via message_statuses
            $unread = $c->messages()
                ->notExpired()
                ->visibleTo($u)
                ->where('sender_id', '!=', $u)
                ->whereDoesntHave('statuses', function ($q) use ($u) {
                    $q->where('user_id', $u)
                      ->where('status', MessageStatus::STATUS_READ);
                })
                ->count();

            return [
                'id'           => $c->id,
                'type'         => 'dm',
                'title'        => $other?->name ?: 'DM #' . $c->id,
                'avatar'       => $other?->avatar_path ? asset('storage/' . $other->avatar_path) : null,
                'last_message' => $last
                    ? mb_strimwidth(
                        $last->is_encrypted ? '[Encrypted]' : (string) $last->body,
                        0,
                        140,
                        '…'
                    )
                    : null,
                'last_at'      => optional($last?->created_at)->toIso8601String(),
                'unread'       => $unread,
            ];
        });

    // Groups
    $groupThreads = Group::query()
        ->whereHas('members', fn ($q) => $q->where('users.id', $u))
        ->get()
        ->map(function ($g) use ($u) {
            $last = $g->messages()
                ->visibleTo($u)
                ->latest()
                ->first();

            $unread = $g->messages()
                ->visibleTo($u)
                ->where('sender_id', '!=', $u)
                ->whereDoesntHave('readers', function ($q) use ($u) {
                    $q->where('user_id', $u);
                })
                ->count();

            return [
                'id'           => $g->id,
                'type'         => 'group',
                'title'        => $g->name,
                'avatar'       => $g->avatar_path ? asset('storage/' . $g->avatar_path) : null,
                'last_message' => $last
                    ? mb_strimwidth((string) $last->body, 0, 140, '…')
                    : null,
                'last_at'      => optional($last?->created_at)->toIso8601String(),
                'unread'       => $unread,
            ];
        });

    // Merge + sort by last message time (desc)
    $threads = $dmThreads->merge($groupThreads)
        ->sortByDesc(fn ($t) => $t['last_at'] ?? '')
        ->values();

    return response()->json(['data' => $threads]);
}


    /**
     * GET /api/v1/chats/{id}/messages?type=dm|group&before=&after=&limit=
     * Uses the new MessageResource payload for consistency with the new API.
     */
    public function messages(Request $r, $id)
    {
        $r->validate([
            'type'  => 'nullable|in:dm,group',
            'before'=> 'nullable|date',
            'after' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);
        $uid   = $r->user()->id;
        $limit = $r->integer('limit', 50);

        if ($r->input('type', 'dm') === 'group') {
            // GROUP MESSAGES
            $g = Group::findOrFail($id);
            abort_unless($g->isMember($uid), 403);

            $q = $g->messages()
                ->with(['sender:id,name,phone,avatar_path','attachments','replyTo','forwardedFrom','reactions.user'])
                ->visibleTo($uid)
                ->orderBy('created_at', 'desc');

            if ($r->filled('before')) $q->where('created_at','<',$r->before);
            if ($r->filled('after'))  $q->where('created_at','>',$r->after);

            $items = $q->limit($limit)->get()->sortBy('created_at')->values();
            return response()->json(['data' => MessageResource::collection($items)]);
        }

        // DM MESSAGES (default)
        $c = Conversation::findOrFail($id);
        abort_unless($c->isParticipant($uid), 403);

        $q = $c->messages()
            ->with(['sender:id,name,phone,avatar_path','attachments','replyTo','forwardedFrom','reactions.user'])
            ->notExpired()
            ->visibleTo($uid)
            ->orderBy('created_at', 'desc');

        if ($r->filled('before')) $q->where('created_at','<',$r->before);
        if ($r->filled('after'))  $q->where('created_at','>',$r->after);

        $items = $q->limit($limit)->get()->sortBy('created_at')->values();

        // Optional: lazy mark as read for DMs
        $c->markMessagesAsRead($uid);

        return response()->json(['data' => MessageResource::collection($items)]);
    }
}
