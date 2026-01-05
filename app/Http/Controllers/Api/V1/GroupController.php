<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    public function index(Request $r)
    {
        $uid = $r->user()->id;
        $groups = Group::query()
            ->whereHas('members', fn($q)=>$q->where('users.id',$uid))
            ->withUnreadCounts($uid)  // Use the correct scope name
            ->latest('updated_at')
            ->get();

        $now = now();
        $data = $groups->map(function($g) use ($uid, $now){
            $last = $g->messages()->visibleTo($uid)->latest()->first();
            // Determine pinned/muted from group_members pivot
            $memberPivot = $g->members()->where('users.id', $uid)->first()?->pivot;
            $isPinned = false;
            $isMuted = false;
            if ($memberPivot) {
                $isPinned = !is_null($memberPivot->pinned_at);
                $isMuted = $memberPivot->muted_until && $memberPivot->muted_until->gt($now);
            }
            // Load members count
            $memberCount = $g->members()->count();
            
            return [
                'id' => $g->id,
                'type' => $g->type ?? 'group',
                'name' => $g->name,
                'avatar' => $g->avatar_path ? asset('storage/'.$g->avatar_path) : null,
                'avatar_url' => $g->avatar_path ? asset('storage/'.$g->avatar_path) : null,
                'member_count' => $memberCount,
                'last_message' => $last ? [
                    'id'=>$last->id,
                    'body_preview'=>mb_strimwidth((string)$last->body,0,140,'…'),
                    'created_at'=>optional($last->created_at)->toIso8601String(),
                ]:null,
                'unread' => $g->getUnreadCountForUser($uid),
                'unread_count' => $g->getUnreadCountForUser($uid),
                'pinned' => $isPinned,
                'muted' => $isMuted,
                'is_verified' => $g->is_verified ?? false,
            ];
        });

        return response()->json(['data'=>$data]);
    }

    /**
     * Create a new group
     * POST /groups
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:64',
            'description' => 'nullable|string|max:200',
            'members'     => 'required|array|min:1',
            'members.*'   => 'integer|exists:users,id',
            'type'        => 'nullable|in:channel,group',
            'is_public'   => 'nullable|boolean',
            'avatar'      => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        try {
            return DB::transaction(function () use ($request, $data) {
                $type = $data['type'] ?? 'group';
                $isPublic = $type === 'channel' ? true : ($data['is_public'] ?? false);

                // Handle avatar upload
                $avatarPath = null;
                if ($request->hasFile('avatar')) {
                    $avatar = $request->file('avatar');
                    $avatarPath = $avatar->store('groups/avatars', 'public');
                }

                $group = Group::create([
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'owner_id' => $request->user()->id,
                    'type' => $type,
                    'is_public' => $isPublic,
                    'avatar_path' => $avatarPath,
                ]);

                // Add creator as admin
                $group->members()->attach($request->user()->id, [
                    'role' => 'admin',
                    'joined_at' => now(),
                ]);

                // Add other members
                if (!empty($data['members'])) {
                    $memberIds = collect($data['members'])
                        ->filter(fn($id) => (int)$id !== (int)$request->user()->id)
                        ->unique()
                        ->values();

                    if ($memberIds->isNotEmpty()) {
                        $attach = [];
                        foreach ($memberIds as $uid) {
                            $attach[$uid] = ['role' => 'member', 'joined_at' => now()];
                        }
                        $group->members()->syncWithoutDetaching($attach);
                    }
                }

                $group->load(['members:id,name,phone,avatar_path']);

                // Generate full URL for avatar if it exists
                $avatarUrl = null;
                if ($group->avatar_path) {
                    $avatarUrl = asset('storage/' . $group->avatar_path);
                }

                return response()->json([
                    'data' => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'type' => $group->type ?? 'group',
                        'avatar_url' => $avatarUrl,
                        'avatar' => $avatarUrl,
                        'member_count' => $group->members->count(),
                        'members' => $group->members->map(function($m) {
                            return [
                                'id' => $m->id,
                                'name' => $m->name,
                                'phone' => $m->phone,
                                'avatar_url' => $m->avatar_path ? asset('storage/' . $m->avatar_path) : null,
                            ];
                        })->values(),
                    ]
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Failed to create group: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to create group: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $r, $id)
    {
        try {
            $uid = $r->user()->id;
            $g = Group::findOrFail($id);
            abort_unless($g->isMember($r->user()), 403);

            $memberPivot = $g->members()->where('users.id', $uid)->first()?->pivot;
            $userRole = $memberPivot?->role ?? 'member';
            $isOwner = $g->owner_id === $uid;
            $isAdmin = $isOwner || $userRole === 'admin';

            $members = $g->members()
                ->withPivot(['role', 'joined_at', 'pinned_at', 'muted_until'])
                ->get()
                ->map(function ($member) use ($g) {
                    try {
                        return [
                            'id' => $member->id,
                            'name' => $member->name,
                            'phone' => $member->phone,
                            'avatar_url' => $member->avatar_path ? asset('storage/' . $member->avatar_path) : null,
                            'role' => $g->owner_id === $member->id ? 'owner' : ($member->pivot->role ?? 'member'),
                            'joined_at' => $member->pivot->joined_at?->toIso8601String(),
                            'is_online' => $member->isOnline(),
                            'last_seen_at' => optional($member->last_seen_at)?->toIso8601String(),
                        ];
                    } catch (\Exception $e) {
                        Log::error('Error processing group member ' . ($member->id ?? 'unknown') . ': ' . $e->getMessage(), [
                            'trace' => $e->getTraceAsString(),
                        ]);
                        return [
                            'id' => $member->id ?? 0,
                            'name' => $member->name ?? 'Unknown',
                            'phone' => $member->phone ?? '',
                            'avatar_url' => null,
                            'role' => 'member',
                            'joined_at' => null,
                            'is_online' => false,
                            'last_seen_at' => null,
                        ];
                    }
                })
                ->filter() // Remove any null entries
                ->values()
                ->toArray(); // Convert to array to ensure proper JSON serialization

            $admins = collect($members)->whereIn('role', ['owner', 'admin'])->values()->toArray();

            return response()->json([
                'data' => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'description' => $g->description,
                    'type' => $g->type ?? 'group',
                    'avatar_url' => $g->avatar_path ? asset('storage/' . $g->avatar_path) : null,
                    'owner_id' => $g->owner_id,
                    'is_public' => $g->is_public ?? false,
                    'is_private' => $g->is_private ?? false,
                    'is_verified' => $g->is_verified ?? false,
                    'invite_code' => $g->invite_code,
                    'member_count' => count($members),
                    'members' => $members,
                    'admins' => $admins,
                    'user_role' => $isOwner ? 'owner' : $userRole,
                    'is_admin' => $isAdmin,
                    'is_owner' => $isOwner,
                    'created_at' => $g->created_at->toIso8601String(),
                    'updated_at' => $g->updated_at->toIso8601String(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get group info: ' . $e->getMessage(), [
                'group_id' => $id,
                'user_id' => $r->user()->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to load group information: ' . $e->getMessage()
            ], 500);
        }
    }

    public function messages(Request $r, $id)
    {
        $r->validate(['before'=>'nullable|date','after'=>'nullable|date','limit'=>'nullable|integer|min:1|max:100']);
        $uid = $r->user()->id;
        $g = Group::findOrFail($id);
        abort_unless($g->isMember($r->user()), 403);

        $q = $g->messages()->with(['sender:id,name,phone,avatar_path','attachments','replyTo','forwardedFrom','reactions.user'])
            ->visibleTo($uid)->orderBy('created_at','desc');

        if ($r->filled('before')) $q->where('created_at','<',$r->before);
        if ($r->filled('after'))  $q->where('created_at','>',$r->after);
        $items = $q->limit($r->integer('limit',50))->get()->sortBy('created_at')->values();

        return response()->json(['data' => MessageResource::collection($items)]);
    }

    /**
     * Update group details (name, description, avatar)
     * PUT /groups/{id}
     */
    public function update(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user = $request->user();
        
        // Check permissions - owner or admin can update
        $memberPivot = $group->members()->where('users.id', $user->id)->first()?->pivot;
        $userRole = $memberPivot?->role ?? 'member';
        $isOwner = $group->owner_id === $user->id;
        $isAdmin = $isOwner || $userRole === 'admin';
        
        abort_unless($isAdmin, 403, 'Only group owners and admins can update group details.');
        
        $data = $request->validate([
            'name' => 'sometimes|string|max:64',
            'description' => 'nullable|string|max:200',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);
        
        try {
            return DB::transaction(function () use ($request, $group, $data) {
                // Handle avatar upload
                if ($request->hasFile('avatar')) {
                    // Delete old avatar if exists
                    if ($group->avatar_path) {
                        \Storage::disk('public')->delete($group->avatar_path);
                    }
                    
                    $avatar = $request->file('avatar');
                    $avatarPath = $avatar->store('groups/avatars', 'public');
                    $data['avatar_path'] = $avatarPath;
                }
                
                $group->update($data);
                $group->load(['members:id,name,phone,avatar_path']);
                
                // Generate full URL for avatar if it exists
                $avatarUrl = null;
                if ($group->avatar_path) {
                    $avatarUrl = asset('storage/' . $group->avatar_path);
                }
                
                return response()->json([
                    'data' => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'description' => $group->description,
                        'type' => $group->type ?? 'group',
                        'avatar_url' => $avatarUrl,
                        'avatar' => $avatarUrl,
                        'member_count' => $group->members->count(),
                    ]
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to update group: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to update group: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Join a public group (channel). Only works if the group is not private.
     * POST /groups/{id}/join
     */
    public function join(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user  = $request->user();
        // Group must exist and be public
        abort_if($group->is_private, 403, 'Private groups cannot be joined without invitation.');

        // Add user if not already a member
        if (!$group->isMember($user)) {
            $group->members()->syncWithoutDetaching([
                $user->id => [
                    'role'      => 'member',
                    'joined_at' => now(),
                ],
            ]);
        }

        return response()->json([
            'status'   => 'success',
            'group_id' => $group->id,
            'joined'   => true,
        ]);
    }

    /**
     * Leave a group. Detach membership for current user. Owners cannot leave their own group.
     * DELETE /groups/{id}/leave
     */
    public function leave(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user  = $request->user();
        abort_unless($group->isMember($user), 403);
        // Owner cannot leave
        if ($group->owner_id === $user->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Group owner cannot leave their own group.',
            ], 422);
        }
        $group->members()->detach($user->id);

        return response()->json([
            'status'   => 'success',
            'group_id' => $group->id,
            'left'     => true,
        ]);
    }

    /**
     * Pin a group conversation for the current user. Sets pinned_at on pivot.
     * POST /groups/{id}/pin
     */
    public function pin(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user  = $request->user();
        abort_unless($group->isMember($user), 403);
        $group->members()->updateExistingPivot($user->id, [
            'pinned_at' => now(),
        ]);
        return response()->json([
            'status'    => 'success',
            'pinned_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Unpin a group conversation for the current user.
     * DELETE /groups/{id}/pin
     */
    public function unpin(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user  = $request->user();
        abort_unless($group->isMember($user), 403);
        $group->members()->updateExistingPivot($user->id, [
            'pinned_at' => null,
        ]);
        return response()->json([
            'status'    => 'success',
            'pinned_at' => null,
        ]);
    }

    /**
     * Mute or unmute a group conversation for the current user. Accepts until or minutes like DM mute.
     * POST /groups/{id}/mute
     */
    public function mute(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user  = $request->user();
        abort_unless($group->isMember($user), 403);

        $pivot = $group->members()->where('users.id', $user->id)->first()?->pivot;
        $mutedUntil = null;
        if ($request->filled('until')) {
            $mutedUntil = \Carbon\Carbon::parse($request->input('until'));
        } elseif ($request->filled('minutes')) {
            $minutes = max((int) $request->input('minutes'), 1);
            $mutedUntil = now()->addMinutes($minutes);
        } else {
            if ($pivot && $pivot->muted_until && $pivot->muted_until->isFuture()) {
                $mutedUntil = null;
            } else {
                $mutedUntil = now()->addYears(5);
            }
        }
        $group->members()->updateExistingPivot($user->id, [
            'muted_until' => $mutedUntil,
        ]);

        return response()->json([
            'status'      => 'success',
            'muted_until' => $mutedUntil ? $mutedUntil->toIso8601String() : null,
        ]);
    }

    /**
     * Update notification settings for a group.
     * PUT /groups/{id}/notification-settings
     */
    public function updateNotificationSettings(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        abort_unless($group->isMember($request->user()), 403);

        $request->validate([
            'muted' => 'sometimes|boolean',
            'muted_until' => 'nullable|date',
        ]);

        $pivotData = [];
        if ($request->has('muted')) {
            if ($request->boolean('muted')) {
                $pivotData['muted_until'] = $request->input('muted_until')
                    ? \Carbon\Carbon::parse($request->input('muted_until'))
                    : now()->addYears(5);
            } else {
                $pivotData['muted_until'] = null;
            }
        }

        if (!empty($pivotData)) {
            $group->members()->updateExistingPivot($request->user()->id, $pivotData);
        }

        return response()->json([
            'message' => 'Notification settings updated',
        ]);
    }

    // GroupController@messages
// public function messages($id, Request $req) {
//     $q = $req->query('q');
//     $query = GroupMessage::where('group_id', $id)->with(['sender','reactions','attachments']);
//     if ($q) {
//         $query->where(function($w) use ($q){
//             $w->where('body','like',"%{$q}%")
//               ->orWhereHas('attachments', fn($a)=>$a->where('original_name','like',"%{$q}%"));
//         });
//     }
//     // keep your before/after/limit logic
//     return GroupMessageResource::collection($query->latest()->paginate(50));
// }

}
