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
            ->with([
                'userOne:id,name,phone,avatar_path',
                'userTwo:id,name,phone,avatar_path',
                'members:id,name,phone,avatar_path', // Load members for otherParticipant() method
                'labels:id,name', // Load labels for filtering
                // Eager load last message to avoid N+1 queries
                'messages' => function($q) use ($u) {
                    $q->notExpired()->visibleTo($u)->latest()->limit(1);
                },
                'messages.sender:id,name,phone,avatar_path', // Load sender for last message
            ])
            ->withMax('messages', 'created_at')
            ->whereNull('conversation_user.archived_at') // Exclude archived conversations
            ->orderByDesc('conversation_user.pinned_at')
            ->orderByDesc('messages_max_created_at')
            ->get();
        
        // Eager load contacts for all conversations to avoid N+1 queries
        $otherUserIds = $convs->map(function($c) use ($u) {
            $other = $c->user_one_id === $u ? $c->userTwo : $c->userOne;
            return $other?->id;
        })->filter()->unique()->values()->toArray();
        
        $contacts = \App\Models\Contact::where('user_id', $u)
            ->whereIn('contact_user_id', $otherUserIds)
            ->get()
            ->keyBy('contact_user_id');

        $now = now();
        $data = $convs->map(function($c) use ($user, $u, $now) {
            try {
                // Use the Conversation model's otherParticipant method for consistency
                // This handles both user_one_id/user_two_id and pivot-based conversations
                $other = $c->otherParticipant();
                
                // If otherParticipant doesn't work, fallback to user_one_id/user_two_id
                if (!$other) {
                    $other = $c->user_one_id === $u ? $c->userTwo : $c->userOne;
                }
                
                // Get title using the model's getTitleAttribute logic (checks contacts)
                // Set Auth user temporarily for getTitleAttribute to work
                $originalUser = \Illuminate\Support\Facades\Auth::user();
                \Illuminate\Support\Facades\Auth::setUser($user);
                $title = $c->title ?? ($other?->name ?? ($other?->phone ?? 'DM #'.$c->id));
                if ($originalUser) {
                    \Illuminate\Support\Facades\Auth::setUser($originalUser);
                } else {
                    \Illuminate\Support\Facades\Auth::logout();
                }
                
                // Check for contact display name for proper naming (same as web app)
                // Use eager loaded contacts to avoid N+1 queries
                if (!$c->is_group && !$c->is_saved_messages && $other) {
                    $contact = $contacts->get($other->id);
                    if ($contact && $contact->display_name) {
                        $title = $contact->display_name;
                    }
                }
                
                // Get last message - use eager loaded message if available
                $last = null;
                if ($c->relationLoaded('messages') && $c->messages->isNotEmpty()) {
                    $last = $c->messages->first();
                } else {
                    // Fallback: load if not eager loaded
                    try {
                        $last = $c->messages()->notExpired()->visibleTo($u)->latest()->first();
                    } catch (\Exception $e) {
                        // Fallback if scopes don't exist
                        $last = $c->messages()->latest()->first();
                    }
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
                // For saved messages, use the current user's avatar
                $avatarUrl = null;
                if ($c->is_saved_messages) {
                    // Saved messages - use current user's avatar
                    if ($user->avatar_path) {
                        try {
                            $avatarUrl = asset('storage/'.$user->avatar_path);
                        } catch (\Exception $e) {
                            $avatarUrl = url('storage/'.$user->avatar_path);
                        }
                    }
                    $otherUserData = [
                        'id' => $user->id,
                        'name' => 'Saved Messages',
                        'phone' => $user->phone,
                        'avatar' => $avatarUrl,
                        'avatar_url' => $avatarUrl,
                        'online' => false,
                        'last_seen_at' => null,
                    ];
                } else if ($other && $other->avatar_path) {
                    try {
                        $avatarUrl = asset('storage/'.$other->avatar_path);
                    } catch (\Exception $e) {
                        // If asset() fails, try direct URL
                        $avatarUrl = url('storage/'.$other->avatar_path);
                    }
                    $otherUserData = [
                        'id' => $other->id,
                        'name' => $other->name ?? $other->phone ?? 'Unknown',
                        'phone' => $other->phone,
                        'avatar' => $avatarUrl,
                        'avatar_url' => $avatarUrl, // Also include avatar_url for consistency
                        'online' => $other->last_seen_at && $other->last_seen_at->gt(now()->subMinutes(5)),
                        'last_seen_at' => optional($other->last_seen_at)?->toIso8601String(),
                    ];
                } else {
                    // Always provide otherUserData if $other exists, even without avatar
                    if ($other) {
                        // Use contact display name if available, otherwise use name or phone
                        $displayName = $other->name ?? $other->phone ?? 'Unknown';
                        if (!$c->is_group && !$c->is_saved_messages) {
                            $contact = $contacts->get($other->id);
                            if ($contact && $contact->display_name) {
                                $displayName = $contact->display_name;
                            }
                        }
                        
                        $otherUserData = [
                            'id' => $other->id,
                            'name' => $displayName,
                            'phone' => $other->phone,
                            'avatar' => null,
                            'avatar_url' => null,
                            'online' => $other->last_seen_at && $other->last_seen_at->gt(now()->subMinutes(5)),
                            'last_seen_at' => optional($other->last_seen_at)?->toIso8601String(),
                        ];
                    } else {
                        $otherUserData = null;
                    }
                }
                
                // Get label IDs for this conversation
                $labelIds = [];
                try {
                    if ($c->relationLoaded('labels')) {
                        $labelIds = $c->labels->pluck('id')->toArray();
                    } else {
                        // Fallback: load labels if not already loaded
                        $labelIds = $c->labels()->pluck('labels.id')->toArray();
                    }
                } catch (\Exception $e) {
                    // If labels relationship doesn't exist or fails, just use empty array
                    \Log::warning('Failed to load labels for conversation ' . $c->id . ': ' . $e->getMessage());
                }
                
                return [
                    'id' => $c->id,
                    'type' => 'dm',
                    'title' => $title,
                    'other_user' => $otherUserData,
                    'last_message' => $last ? [
                        'id' => $last->id,
                        'body_preview' => mb_strimwidth($last->is_encrypted ? '[Encrypted]' : (string)($last->body ?? ''), 0, 140, '…'),
                        'created_at' => optional($last->created_at)->toIso8601String(),
                    ] : null,
                    'unread' => $unread,
                    'pinned' => $isPinned,
                    'muted' => $isMuted,
                    'labels' => $labelIds, // Include label IDs for filtering
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
                    'labels' => [], // Empty labels array on error
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
        $user = $r->user();
        $u = $user->id;
        $conv = Conversation::findOrFail($id);
        abort_unless($conv->isParticipant($u), 403);
        
        // Load the same data as index method for consistency
        $conv->load([
            'userOne:id,name,phone,avatar_path',
            'userTwo:id,name,phone,avatar_path',
            'members:id,name,phone,avatar_path',
            'labels:id,name',
            'messages' => function($q) use ($u) {
                $q->notExpired()->visibleTo($u)->latest()->limit(1);
            },
            'messages.sender:id,name,phone,avatar_path',
        ]);
        
        $now = now();
        
        try {
            // Use the same logic as index method
            $other = $conv->otherParticipant();
            if (!$other) {
                $other = $conv->user_one_id === $u ? $conv->userTwo : $conv->userOne;
            }
            
            // Get title
            $originalUser = \Illuminate\Support\Facades\Auth::user();
            \Illuminate\Support\Facades\Auth::setUser($user);
            $title = $conv->title ?? ($other?->name ?? ($other?->phone ?? 'DM #'.$conv->id));
            if ($originalUser) {
                \Illuminate\Support\Facades\Auth::setUser($originalUser);
            } else {
                \Illuminate\Support\Facades\Auth::logout();
            }
            
            // Check for contact display name
            if (!$conv->is_group && !$conv->is_saved_messages && $other) {
                $contact = \App\Models\Contact::where('user_id', $u)
                    ->where('contact_user_id', $other->id)
                    ->first();
                if ($contact && $contact->display_name) {
                    $title = $contact->display_name;
                }
            }
            
            // Get last message
            $last = null;
            if ($conv->relationLoaded('messages') && $conv->messages->isNotEmpty()) {
                $last = $conv->messages->first();
            } else {
                try {
                    $last = $conv->messages()->notExpired()->visibleTo($u)->latest()->first();
                } catch (\Exception $e) {
                    $last = $conv->messages()->latest()->first();
                }
            }
            
            // Get unread count
            $unread = 0;
            try {
                $unread = $conv->unreadCountFor($u);
            } catch (\Exception $e) {
                try {
                    $unread = $conv->messages()
                        ->where('sender_id','!=',$u)
                        ->whereDoesntHave('statuses', function($q) use ($u) {
                            $q->where('user_id', $u)
                              ->where('status', \App\Models\MessageStatus::STATUS_READ);
                        })
                        ->count();
                } catch (\Exception $e2) {
                    $unread = $conv->messages()->where('sender_id','!=',$u)->count();
                }
            }
            
            // Get pivot data
            $isPinned = false;
            $isMuted = false;
            $archivedAt = null;
            
            try {
                $pivot = $conv->pivot;
                if ($pivot) {
                    $isPinned = !is_null($pivot->pinned_at);
                    if ($pivot->muted_until) {
                        $mutedUntil = \Carbon\Carbon::parse($pivot->muted_until);
                        $isMuted = $mutedUntil->gt($now);
                    }
                    $archivedAt = $pivot->archived_at;
                }
            } catch (\Exception $e) {
                try {
                    $pivotData = \DB::table('conversation_user')
                        ->where('conversation_id', $conv->id)
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
                    \Log::warning('Failed to get pivot data for conversation ' . $conv->id . ': ' . $e2->getMessage());
                }
            }
            
            // Build other user data
            $otherUserData = null;
            if ($other) {
                $avatarUrl = $other->avatar_path 
                    ? asset('storage/'.$other->avatar_path) 
                    : null;
                
                $otherUserData = [
                    'id' => $other->id,
                    'name' => $other->name ?? $other->phone ?? 'Unknown',
                    'phone' => $other->phone,
                    'avatar' => null,
                    'avatar_url' => $avatarUrl,
                    'online' => $other->last_seen_at && $other->last_seen_at->gt(now()->subMinutes(5)),
                    'last_seen_at' => optional($other->last_seen_at)?->toIso8601String(),
                ];
            }
            
            // Get label IDs
            $labelIds = [];
            try {
                if ($conv->relationLoaded('labels')) {
                    $labelIds = $conv->labels->pluck('id')->toArray();
                } else {
                    $labelIds = $conv->labels()->pluck('labels.id')->toArray();
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to load labels for conversation ' . $conv->id . ': ' . $e->getMessage());
            }
            
            return response()->json([
                'data' => [
                    'id' => $conv->id,
                    'type' => 'dm',
                    'title' => $title,
                    'other_user' => $otherUserData,
                    'other_user_id' => $other?->id, // Include for backward compatibility
                    'last_message' => $last ? [
                        'id' => $last->id,
                        'body_preview' => mb_strimwidth($last->is_encrypted ? '[Encrypted]' : (string)($last->body ?? ''), 0, 140, '…'),
                        'created_at' => optional($last->created_at)->toIso8601String(),
                    ] : null,
                    'unread' => $unread,
                    'unread_count' => $unread, // Include for backward compatibility
                    'updated_at' => optional($last?->created_at ?? $conv->updated_at)->toIso8601String(),
                    'pinned' => $isPinned,
                    'muted' => $isMuted,
                    'archived_at' => optional($archivedAt)->toIso8601String(),
                    'labels' => $labelIds,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error processing conversation ' . $conv->id . ' in show method: ' . $e->getMessage());
            // Return minimal data on error
            return response()->json([
                'data' => [
                    'id' => $conv->id,
                    'type' => 'dm',
                    'title' => 'DM #'.$conv->id,
                    'other_user' => null,
                    'other_user_id' => null,
                    'last_message' => null,
                    'unread' => 0,
                    'unread_count' => 0,
                    'pinned' => false,
                    'muted' => false,
                    'archived_at' => null,
                    'labels' => [],
                ]
            ]);
        }
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
        
        // Reset last_read_message_id to null, which will make all messages appear as unread
        $conv->members()->updateExistingPivot($userId, [
            'last_read_message_id' => null
        ]);
        
        // Also delete message statuses to mark as unread for consistency
        $conv->messages()
            ->where('sender_id', '!=', $userId)
            ->get()
            ->each(function ($message) use ($userId) {
                // Delete status to mark as unread
                $message->statuses()->where('user_id', $userId)->delete();
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
        $user = $request->user();
        $u = $user->id;

        // Use the same relationship as main conversations endpoint for consistency
        // This ensures both API endpoints use the same conversation_user pivot table
        $convs = $user->conversations()
            ->with([
                'userOne:id,name,phone,avatar_path',
                'userTwo:id,name,phone,avatar_path',
                'members:id,name,phone,avatar_path', // Load members for otherParticipant() method
                'labels:id,name', // Load labels for filtering
                // Eager load last message to avoid N+1 queries
                'messages' => function($q) use ($u) {
                    $q->notExpired()->visibleTo($u)->latest()->limit(1);
                },
                'messages.sender:id,name,phone,avatar_path', // Load sender for last message
            ])
            ->withMax('messages', 'created_at')
            ->whereNotNull('conversation_user.archived_at') // Only include archived conversations
            ->orderByDesc('conversation_user.pinned_at')
            ->orderByDesc('messages_max_created_at')
            ->get();
        
        // Eager load contacts for all conversations to avoid N+1 queries
        $otherUserIds = $convs->map(function($c) use ($u) {
            $other = $c->user_one_id === $u ? $c->userTwo : $c->userOne;
            return $other?->id;
        })->filter()->unique()->values()->toArray();
        
        $contacts = \App\Models\Contact::where('user_id', $u)
            ->whereIn('contact_user_id', $otherUserIds)
            ->get()
            ->keyBy('contact_user_id');

        $now = now();
        $data = $convs->map(function($c) use ($u, $now) {
            try {
                $other = $c->user_one_id === $u ? $c->userTwo : $c->userOne;
                
                // Get label IDs for this conversation
                $labelIds = [];
                try {
                    if ($c->relationLoaded('labels')) {
                        $labelIds = $c->labels->pluck('id')->toArray();
                    } else {
                        // Fallback: load labels if not already loaded
                        $labelIds = $c->labels()->pluck('labels.id')->toArray();
                    }
                } catch (\Exception $e) {
                    // If labels relationship doesn't exist or fails, just use empty array
                    \Log::warning('Failed to load labels for archived conversation ' . $c->id . ': ' . $e->getMessage());
                }
                
                // Get last message - use eager loaded message if available
                $last = null;
                if ($c->relationLoaded('messages') && $c->messages->isNotEmpty()) {
                    $last = $c->messages->first();
                } else {
                    // Fallback: load if not eager loaded
                    try {
                        $last = $c->messages()->notExpired()->visibleTo($u)->latest()->first();
                    } catch (\Exception $e) {
                        $last = $c->messages()->latest()->first();
                    }
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

                // Use already loaded members relationship to avoid N+1 query
                $pivotData = $c->members->firstWhere('id', $u)?->pivot ?? $c->members()->where('users.id', $u)->first()?->pivot;
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
                    'labels' => $labelIds ?? [], // Include label IDs for filtering
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
                    'labels' => [], // Empty labels array on error
                ];
            }
        });

        return response()->json(['data'=>$data]);
    }

    /**
     * Export conversation messages as text
     * GET /conversations/{id}/export
     */
    public function export(Request $request, $id)
    {
        $conv = Conversation::findOrFail($id);
        abort_unless($conv->isParticipant($request->user()->id), 403);

        $userId = $request->user()->id;
        $messages = $conv->messages()
            ->with(['sender:id,name,phone'])
            ->notExpired()
            ->visibleTo($userId)
            ->orderBy('created_at', 'asc')
            ->get();

        $otherUser = $conv->otherParticipant();
        $otherUserName = $otherUser ? ($otherUser->name ?? $otherUser->phone ?? 'Unknown') : 'Unknown';
        
        $content = "Chat Export: {$otherUserName}\n";
        $content .= "Exported on: " . now()->toDateTimeString() . "\n";
        $content .= str_repeat("=", 50) . "\n\n";

        foreach ($messages as $msg) {
            $senderName = $msg->sender ? ($msg->sender->name ?? $msg->sender->phone ?? 'Unknown') : 'Unknown';
            $timestamp = $msg->created_at->format('Y-m-d H:i:s');
            $body = $msg->is_encrypted ? '[Encrypted]' : ($msg->body ?? '');
            
            if ($msg->attachments->isNotEmpty()) {
                $attCount = $msg->attachments->count();
                $body .= " [{$attCount} attachment" . ($attCount > 1 ? 's' : '') . "]";
            }
            
            $content .= "[{$timestamp}] {$senderName}: {$body}\n";
        }

        $filename = 'chat_export_' . $id . '_' . now()->format('Y-m-d') . '.txt';

        return response($content)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
    
}
