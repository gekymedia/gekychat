<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $r)
    {
        $user = $r->user();
        $u = $user->id;

        // Use the same relationship as web app for consistency
        // This ensures both API and web use the same conversation_user pivot table
        $convs = $user->conversations()
            ->with(['userOne:id,name,phone,avatar_path','userTwo:id,name,phone,avatar_path'])
            ->withMax('messages', 'created_at')
            ->orderByDesc('conversation_user.pinned_at')
            ->orderByDesc('messages_max_created_at')
            ->get();

        $now = now();
        $data = $convs->map(function($c) use ($u, $now) {
            try {
                $other = $c->user_one_id === $u ? $c->userTwo : $c->userOne;
                
                // Get last message - handle cases where scopes might not exist
                $last = null;
                try {
                    $last = $c->messages()->notExpired()->visibleTo($u)->latest()->first();
                } catch (\Exception $e) {
                    // Fallback if scopes don't exist
                    $last = $c->messages()->latest()->first();
                }
                
                // Get unread count using the model's method (uses last_read_message_id from pivot)
                $unread = 0;
                try {
                    $unread = $c->unreadCountFor($u);
                } catch (\Exception $e) {
                    // Fallback: count messages that don't have read status
                    try {
                        $unread = $c->messages()
                            ->where('sender_id','!=',$u)
                            ->whereDoesntHave('statuses', function($q) use ($u) {
                                $q->where('user_id', $u)
                                  ->where('status', \App\Models\MessageStatus::STATUS_READ);
                            })
                            ->count();
                    } catch (\Exception $e2) {
                        // Last resort: simple count
                        $unread = $c->messages()->where('sender_id','!=',$u)->count();
                    }
                }

            // Determine pinned/muted status from pivot (now available via relationship)
            $isPinned = false;
            $isMuted = false;
            $archivedAt = null;
            
            try {
                // Use the pivot data from the relationship (already loaded)
                $pivot = $c->pivot;
                if ($pivot) {
                    $isPinned = !is_null($pivot->pinned_at);
                    if ($pivot->muted_until) {
                        $mutedUntil = \Carbon\Carbon::parse($pivot->muted_until);
                        $isMuted = $mutedUntil->gt($now);
                    }
                    $archivedAt = $pivot->archived_at;
                }
            } catch (\Exception $e) {
                // Fallback: try direct query if pivot not available
                try {
                    $pivotData = \DB::table('conversation_user')
                        ->where('conversation_id', $c->id)
                        ->where('user_id', $u)
                        ->first(['pinned_at', 'muted_until', 'archived_at']);
                    
                    if ($pivotData) {
                        $isPinned = !is_null($pivotData->pinned_at);
                        if ($pivotData->muted_until) {
                            $mutedUntil = \Carbon\Carbon::parse($pivotData->muted_until);
                            $isMuted = $mutedUntil->gt($now);
                        }
                        $archivedAt = $pivotData->archived_at;
                    }
                } catch (\Exception $e2) {
                    \Log::warning('Failed to get pivot data for conversation ' . $c->id . ': ' . $e2->getMessage());
                }
            }

                // Build avatar URL safely
                $avatarUrl = null;
                if ($other && $other->avatar_path) {
                    try {
                        $avatarUrl = asset('storage/'.$other->avatar_path);
                    } catch (\Exception $e) {
                        // If asset() fails, try direct URL
                        $avatarUrl = url('storage/'.$other->avatar_path);
                    }
                }
                
                return [
                    'id' => $c->id,
                    'type' => 'dm',
                    'title' => $other?->name ?? ($other?->phone ?? 'DM #'.$c->id),
                    'other_user' => $other ? [
                        'id'=>$other->id,
                        'name'=>$other->name ?? $other->phone ?? 'Unknown',
                        'phone'=>$other->phone,
                        'avatar' => $avatarUrl,
                        'avatar_url' => $avatarUrl, // Also include avatar_url for consistency
                        'online' => $other->last_seen_at && $other->last_seen_at->gt(now()->subMinutes(5)),
                        'last_seen_at' => optional($other->last_seen_at)?->toIso8601String(),
                    ] : null,
                    'last_message' => $last ? [
                        'id' => $last->id,
                        'body_preview' => mb_strimwidth($last->is_encrypted ? '[Encrypted]' : (string)($last->body ?? ''), 0, 140, '…'),
                        'created_at' => optional($last->created_at)->toIso8601String(),
                    ] : null,
                    'unread' => $unread,
                    'pinned' => $isPinned,
                    'muted' => $isMuted,
                ];
            } catch (\Exception $e) {
                // If anything fails, return minimal data
                \Log::error('Error processing conversation ' . $c->id . ': ' . $e->getMessage());
                return [
                    'id' => $c->id,
                    'type' => 'dm',
                    'title' => 'DM #'.$c->id,
                    'other_user' => null,
                    'last_message' => null,
                    'unread' => 0,
                    'pinned' => false,
                    'muted' => false,
                ];
            }
        });

        return response()->json(['data' => $data]);
    }

    public function start(Request $r)
    {
        $r->validate(['user_id' => 'required|exists:users,id|different:'.$r->user()->id]);
        $a = min($r->user()->id, (int)$r->user_id);
        $b = max($r->user()->id, (int)$r->user_id);
        
        // Use findOrCreateDirect which properly syncs to conversation_user pivot table
        $conv = Conversation::findOrCreateDirect($a, $b, $r->user()->id);
        
        return response()->json(['data' => ['id'=>$conv->id]]);
    }

    public function show(Request $r, $id)
    {
        $conv = Conversation::findOrFail($id);
        abort_unless($conv->isParticipant($r->user()->id), 403);
        return response()->json(['data' => ['id'=>$conv->id]]);
    }

    public function messages(Request $r, $id)
    {
        $r->validate(['before'=>'nullable|date','after'=>'nullable|date','limit'=>'nullable|integer|min:1|max:100']);
        $u = $r->user()->id;
        $conv = Conversation::findOrFail($id);
        abort_unless($conv->isParticipant($u), 403);

        $q = $conv->messages()->with(['sender:id,name,phone,avatar_path','attachments','replyTo','forwardedFrom','reactions.user'])
            ->notExpired()->visibleTo($u)->orderBy('created_at','desc');

        if ($r->filled('before')) $q->where('created_at','<',$r->before);
        if ($r->filled('after'))  $q->where('created_at','>',$r->after);
        $items = $q->limit($r->integer('limit',50))->get()->sortBy('created_at')->values();

        // lazy mark as read
        $conv->markMessagesAsRead($u);

        return response()->json(['data' => MessageResource::collection($items)]);
    }

    /**
     * Pin a direct conversation for the authenticated user. Sets the pinned_at timestamp on the pivot.
     * POST /conversations/{id}/pin
     */
    public function pin(Request $request, $id)
    {
        $conv = Conversation::findOrFail($id);
        abort_unless($conv->isParticipant($request->user()->id), 403);

        // Update pivot: set pinned_at to now for this user
        $conv->members()->updateExistingPivot($request->user()->id, [
            'pinned_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'pinned_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Unpin a direct conversation for the authenticated user. Clears the pinned_at timestamp on the pivot.
     * DELETE /conversations/{id}/pin
     */
    public function unpin(Request $request, $id)
    {
        $conv = Conversation::findOrFail($id);
        abort_unless($conv->isParticipant($request->user()->id), 403);

        $conv->members()->updateExistingPivot($request->user()->id, [
            'pinned_at' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'pinned_at' => null,
        ]);
    }

    /**
     * Mute or unmute a direct conversation for the authenticated user.
     * POST /conversations/{id}/mute
     * Body: { "until": "2025-12-31T23:59:59Z" } or { "minutes": 1440 }.
     * If no until or minutes provided, will toggle: unmute if currently muted, else mute indefinitely.
     */
    public function mute(Request $request, $id)
    {
        $conv = Conversation::findOrFail($id);
        abort_unless($conv->isParticipant($request->user()->id), 403);

        $userId = $request->user()->id;
        $pivot  = $conv->members()->where('users.id', $userId)->first()?->pivot;

        // Determine new muted_until value
        $mutedUntil = null;
        if ($request->filled('until')) {
            // Parse provided timestamp
            $mutedUntil = \Carbon\Carbon::parse($request->input('until'));
        } elseif ($request->filled('minutes')) {
            // Mute for given minutes
            $minutes = max((int) $request->input('minutes'), 1);
            $mutedUntil = now()->addMinutes($minutes);
        } else {
            // Toggle: if currently muted and in the future, unmute; else mute indefinitely (5 years)
            if ($pivot && $pivot->muted_until && $pivot->muted_until->isFuture()) {
                $mutedUntil = null;
            } else {
                // Default mute duration: 5 years
                $mutedUntil = now()->addYears(5);
            }
        }

        $conv->members()->updateExistingPivot($userId, [
            'muted_until' => $mutedUntil,
        ]);

        return response()->json([
            'status'      => 'success',
            'muted_until' => $mutedUntil ? $mutedUntil->toIso8601String() : null,
        ]);
    }

    /**
     * Mark a conversation as unread for the authenticated user.
     * POST /conversations/{id}/mark-unread
     */
    public function markUnread(Request $request, $id)
    {
        $conv = Conversation::findOrFail($id);
        abort_unless($conv->isParticipant($request->user()->id), 403);

        $userId = $request->user()->id;
        
        // Mark all messages in this conversation as unread for this user
        $conv->messages()
            ->where('sender_id', '!=', $userId)
            ->get()
            ->each(function ($message) use ($userId) {
                $message->statuses()->where('user_id', $userId)->update(['read_at' => null]);
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Conversation marked as unread',
        ]);
    }

    /**
     * Archive a conversation for the authenticated user.
     * POST /conversations/{id}/archive
     */
    public function archive(Request $request, $id)
    {
        $conv = Conversation::findOrFail($id);
        abort_unless($conv->isParticipant($request->user()->id), 403);

        $conv->members()->updateExistingPivot($request->user()->id, [
            'archived_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Conversation archived',
            'archived_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Unarchive a conversation for the authenticated user.
     * DELETE /conversations/{id}/archive
     */
    public function unarchive(Request $request, $id)
    {
        $conv = Conversation::findOrFail($id);
        abort_unless($conv->isParticipant($request->user()->id), 403);

        $conv->members()->updateExistingPivot($request->user()->id, [
            'archived_at' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Conversation unarchived',
            'archived_at' => null,
        ]);
    }

    /**
     * Get archived conversations for the authenticated user.
     * GET /conversations/archived
     */
    public function archived(Request $request)
    {
        $u = $request->user()->id;

        $convs = Conversation::query()
            ->where(fn($q)=>$q->where('user_one_id',$u)->orWhere('user_two_id',$u))
            ->whereHas('members', function($q) use ($u) {
                $q->where('users.id', $u)
                  ->whereNotNull('conversation_user.archived_at');
            })
            ->with(['userOne:id,name,phone,avatar_path','userTwo:id,name,phone,avatar_path'])
            ->orderByDesc(
                Message::select('created_at')->whereColumn('messages.conversation_id','conversations.id')->latest()->take(1)
            )
            ->get();

        $now = now();
        $data = $convs->map(function($c) use ($u, $now) {
            try {
                $other = $c->user_one_id === $u ? $c->userTwo : $c->userOne;
                
                $last = null;
                try {
                    $last = $c->messages()->notExpired()->visibleTo($u)->latest()->first();
                } catch (\Exception $e) {
                    $last = $c->messages()->latest()->first();
                }
                
                $unread = 0;
                try {
                    $unread = $c->unreadCountFor($u);
                } catch (\Exception $e) {
                    try {
                        $unread = $c->messages()
                            ->where('sender_id','!=',$u)
                            ->whereDoesntHave('statuses', function($q) use ($u) {
                                $q->where('user_id', $u)
                                  ->where('status', \App\Models\MessageStatus::STATUS_READ);
                            })
                            ->count();
                    } catch (\Exception $e2) {
                        $unread = 0;
                    }
                }

                $pivotData = $c->members()->where('users.id', $u)->first()?->pivot;
                $isPinned = !is_null($pivotData?->pinned_at);
                $isMuted = false;
                if ($pivotData?->muted_until) {
                    $mutedUntil = \Carbon\Carbon::parse($pivotData->muted_until);
                    $isMuted = $mutedUntil->gt($now);
                }
                $archivedAt = $pivotData?->archived_at;

                $avatarUrl = $other?->avatar_path 
                    ? asset('storage/'.$other->avatar_path) 
                    : null;

                return [
                    'id' => $c->id,
                    'type' => 'dm',
                    'title' => $other?->name ?? ($other?->phone ?? 'DM #'.$c->id),
                    'other_user' => $other ? [
                        'id'=>$other->id,
                        'name'=>$other->name ?? $other->phone ?? 'Unknown',
                        'phone'=>$other->phone,
                        'avatar' => $avatarUrl,
                        'avatar_url' => $avatarUrl,
                        'online' => $other->last_seen_at && $other->last_seen_at->gt(now()->subMinutes(5)),
                        'last_seen_at' => optional($other->last_seen_at)?->toIso8601String(),
                    ] : null,
                    'last_message' => $last ? [
                        'id' => $last->id,
                        'body_preview' => mb_strimwidth($last->is_encrypted ? '[Encrypted]' : (string)($last->body ?? ''), 0, 140, '…'),
                        'created_at' => optional($last->created_at)->toIso8601String(),
                    ] : null,
                    'unread' => $unread,
                    'pinned' => $isPinned,
                    'muted' => $isMuted,
                    'archived_at' => $archivedAt ? \Carbon\Carbon::parse($archivedAt)->toIso8601String() : null,
                ];
            } catch (\Exception $e) {
                \Log::error('Error processing archived conversation ' . $c->id . ': ' . $e->getMessage());
                return [
                    'id' => $c->id,
                    'type' => 'dm',
                    'title' => 'DM #'.$c->id,
                    'other_user' => null,
                    'last_message' => null,
                    'unread' => 0,
                    'pinned' => false,
                    'muted' => false,
                    'archived_at' => null,
                ];
            }
        });

        return response()->json(['data'=>$data]);
    }
    
}
