<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Events\GroupMessageReadEvent;
use App\Models\Group;
use App\Models\ChannelFollower;
use App\Models\GroupJoinRequest;
use App\Services\PrivacyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    public function index(Request $r)
    {
        try {
            $uid = $r->user()->id;
            $query = Group::query()
                ->whereHas('members', fn($q)=>$q->where('users.id',$uid))
                ->latest('updated_at');

            // Delta sync: only return groups with these IDs (from GET /sync/changes)
            $ids = $r->input('ids');
            if (is_array($ids) && !empty($ids)) {
                $query->whereIn('id', array_map('intval', $ids));
            } elseif (is_string($ids) && $ids !== '') {
                $query->whereIn('id', array_map('intval', explode(',', $ids)));
            }

            $groups = $query->get();

            $now = now();
            $data = $groups->map(function($g) use ($uid, $now){
                try {
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
                    
                    // Safely get unread count
                    $unreadCount = 0;
                    try {
                        $unreadCount = $g->getUnreadCountForUser($uid);
                    } catch (\Exception $e) {
                        Log::warning('Failed to get unread count for group ' . $g->id . ': ' . $e->getMessage());
                    }

                    // Labels assigned to this group (for labeled list filter)
                    $labelIds = $g->labels()->where('labels.user_id', $uid)->pluck('labels.id')->toArray();
                    
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
                        'unread' => $unreadCount,
                        'unread_count' => $unreadCount,
                        'pinned' => $isPinned,
                        'muted' => $isMuted,
                        'is_verified' => $g->is_verified ?? false,
                        'labels' => array_map(fn($id) => ['id' => $id], $labelIds),
                    ];
                } catch (\Exception $e) {
                    Log::error('Error processing group ' . $g->id . ': ' . $e->getMessage());
                    // Return minimal data for this group
                    return [
                        'id' => $g->id,
                        'type' => $g->type ?? 'group',
                        'name' => $g->name ?? 'Unknown',
                        'avatar' => null,
                        'avatar_url' => null,
                        'member_count' => 0,
                        'last_message' => null,
                        'unread' => 0,
                        'unread_count' => 0,
                        'pinned' => false,
                        'muted' => false,
                        'is_verified' => false,
                        'labels' => [],
                    ];
                }
            });

            return response()->json(['data'=>$data]);
        } catch (\Exception $e) {
            Log::error('Failed to load groups: ' . $e->getMessage(), [
                'user_id' => $r->user()->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to load groups',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create a new group
     * POST /groups
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'name'        => 'required|string|max:64',
                'description' => 'nullable|string|max:200',
                // Channels are created with no initial members (followers opt in via invite link).
                // Groups still require at least one other member — enforced below.
                'members'     => 'nullable|array',
                'members.*'   => 'integer|exists:users,id',
                'type'        => 'nullable|in:channel,group',
                'is_public'   => 'nullable|boolean',
                'avatar'      => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
            ]);

            $requestedType = $data['type'] ?? 'group';
            $memberIds = $data['members'] ?? [];
            if ($requestedType === 'group' && count($memberIds) < 1) {
                throw ValidationException::withMessages([
                    'members' => ['Add at least one member to create a group.'],
                ]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Group creation validation failed', [
                'errors' => $e->errors(),
                'user_id' => $request->user()->id ?? null,
                'request_data' => $request->except(['avatar']),
            ]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

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

                // Add creator as admin member (for both groups and channels)
                $group->members()->attach($request->user()->id, [
                    'role' => 'admin',
                    'joined_at' => now(),
                ]);

                // For channels, also add creator to channel_followers table
                if ($type === 'channel') {
                    ChannelFollower::create([
                        'channel_id' => $group->id,
                        'user_id' => $request->user()->id,
                        'followed_at' => now(),
                    ]);
                }

                // Add other members (only for groups, not channels)
                // Channels are opt-in only - people must choose to follow via invite link
                // You cannot force-add followers to a channel (like you can't force followers on Facebook)
                if (!empty($data['members']) && $type !== 'channel') {
                    $memberIds = collect($data['members'])
                        ->filter(fn($id) => (int)$id !== (int)$request->user()->id)
                        ->unique()
                        ->values();

                    // Check group member limit (5,000 for groups)
                    // +1 for the creator who is already added
                    $totalMembers = $memberIds->count() + 1;
                    if ($totalMembers > Group::MAX_GROUP_MEMBERS) {
                        throw new \Exception('Cannot create group with more than ' . Group::MAX_GROUP_MEMBERS . ' members.');
                    }

                    if ($memberIds->isNotEmpty()) {
                        $attach = [];
                        foreach ($memberIds as $uid) {
                            $attach[$uid] = ['role' => 'member', 'joined_at' => now()];
                            // Create system message for each new member
                            $group->createSystemMessage('joined', $uid);
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

            $viewer = $r->user();
            $members = $g->members()
                ->withPivot(['role', 'joined_at', 'pinned_at', 'muted_until'])
                ->get()
                ->map(function ($member) use ($g, $viewer) {
                    try {
                        // Handle joined_at - it might be a string or Carbon instance
                        $joinedAt = null;
                        if ($member->pivot->joined_at) {
                            if (is_string($member->pivot->joined_at)) {
                                $joinedAt = $member->pivot->joined_at;
                            } else {
                                $joinedAt = $member->pivot->joined_at->toIso8601String();
                            }
                        }
                        
                        // Check privacy settings for member data
                        $canSeeLastSeen = PrivacyService::canSeeLastSeen($viewer, $member);
                        $canSeeProfilePhoto = PrivacyService::canSeeProfilePhoto($viewer, $member);
                        $canSeeOnlineStatus = PrivacyService::canSeeOnlineStatus($viewer, $member);
                        
                        return [
                            'id' => $member->id,
                            'name' => $member->name,
                            'phone' => $member->phone,
                            'avatar_url' => $canSeeProfilePhoto && $member->avatar_path ? asset('storage/' . $member->avatar_path) : null,
                            'role' => $g->owner_id === $member->id ? 'owner' : ($member->pivot->role ?? 'member'),
                            'joined_at' => $joinedAt,
                            'is_online' => $canSeeOnlineStatus && $member->isOnline(),
                            'last_seen_at' => $canSeeLastSeen ? optional($member->last_seen_at)?->toIso8601String() : null,
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
            
            // For channels, hide member list from non-admins (privacy like Telegram/WhatsApp)
            $isChannel = ($g->type ?? 'group') === 'channel';
            $canViewMembers = $isAdmin || !$isChannel;
            
            // If user can't view members, only return the count
            $membersResponse = $canViewMembers ? $members : [];
            $adminsResponse = $canViewMembers ? $admins : [];

            // Get pending join requests count for admins
            $pendingRequestsCount = 0;
            if ($isAdmin && ($g->type ?? 'group') === 'group') {
                $pendingRequestsCount = $g->pendingJoinRequests()->count();
            }

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
                    'members' => $membersResponse,
                    'admins' => $adminsResponse,
                    'members_hidden' => !$canViewMembers, // Flag to indicate members are hidden for privacy
                    'user_role' => $isOwner ? 'owner' : $userRole,
                    'is_admin' => $isAdmin,
                    'is_owner' => $isOwner,
                    'message_lock' => $g->message_lock ?? false, // Message lock status
                    'require_approval' => $g->require_approval ?? false, // Admin approval required to join
                    'pending_requests_count' => $pendingRequestsCount, // Pending join requests (admins only)
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
            // Check if approval is required for this group
            if ($group->require_approval && $group->type === 'group') {
                // Check if user already has a pending request
                if ($group->hasPendingRequestFrom($user->id)) {
                    return response()->json([
                        'status' => 'pending',
                        'message' => 'Your join request is pending admin approval.',
                        'group_id' => $group->id,
                        'joined' => false,
                        'request_pending' => true,
                    ]);
                }
                
                // Create a join request instead of directly joining
                $joinRequest = GroupJoinRequest::create([
                    'group_id' => $group->id,
                    'user_id' => $user->id,
                    'message' => $request->input('message'),
                    'status' => GroupJoinRequest::STATUS_PENDING,
                ]);
                
                return response()->json([
                    'status' => 'pending',
                    'message' => 'Your join request has been submitted. An admin will review it.',
                    'group_id' => $group->id,
                    'joined' => false,
                    'request_id' => $joinRequest->id,
                    'request_pending' => true,
                ]);
            }
            
            // Check group member limit (5,000 for groups, unlimited for channels)
            if (!$group->canAddMembers(1)) {
                $typeLabel = $group->type === 'channel' ? 'channel' : 'group';
                return response()->json([
                    'status' => 'error',
                    'message' => "This {$typeLabel} has reached its maximum member limit of " . Group::MAX_GROUP_MEMBERS . ".",
                ], 422);
            }
            
            $group->members()->syncWithoutDetaching([
                $user->id => [
                    'role'      => 'member',
                    'joined_at' => now(),
                ],
            ]);
            
            // For channels, also add to channel_followers table
            if ($group->type === 'channel') {
                ChannelFollower::firstOrCreate(
                    ['channel_id' => $group->id, 'user_id' => $user->id],
                    ['followed_at' => now()]
                );
            }
            
            // Create system message when user joins
            $group->createSystemMessage('joined', $user->id);
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
        
        // For channels, also remove from channel_followers table
        if ($group->type === 'channel') {
            ChannelFollower::where('channel_id', $group->id)
                ->where('user_id', $user->id)
                ->delete();
        }
        
        // Create system message when user leaves
        $group->createSystemMessage('left', $user->id);

        return response()->json([
            'status'   => 'success',
            'group_id' => $group->id,
            'left'     => true,
        ]);
    }
    
    /**
     * Toggle message lock for a group (only admins can send when enabled)
     * PUT /groups/{id}/message-lock
     */
    public function toggleMessageLock(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user = $request->user();
        
        // Check permissions - owner or admin can toggle message lock
        $memberPivot = $group->members()->where('users.id', $user->id)->first()?->pivot;
        $userRole = $memberPivot?->role ?? 'member';
        $isOwner = $group->owner_id === $user->id;
        $isAdmin = $isOwner || $userRole === 'admin';
        
        abort_unless($isAdmin, 403, 'Only group owners and admins can toggle message lock.');
        
        $request->validate([
            'enabled' => 'required|boolean',
        ]);
        
        $group->update(['message_lock' => $request->boolean('enabled')]);
        
        return response()->json([
            'success' => true,
            'message' => $group->message_lock 
                ? 'Message lock enabled. Only admins can send messages.' 
                : 'Message lock disabled. All members can send messages.',
            'message_lock' => $group->message_lock,
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

    /**
     * Generate or regenerate invite link for group
     * POST /groups/{id}/generate-invite
     */
    public function generateInvite(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user = $request->user();
        
        // Check permissions - owner or admin can generate invite for groups
        // For channels, any member can generate invite links
        $memberPivot = $group->members()->where('users.id', $user->id)->first()?->pivot;
        $userRole = $memberPivot?->role ?? 'member';
        $isOwner = $group->owner_id === $user->id;
        $isAdmin = $isOwner || $userRole === 'admin';
        $isMember = $memberPivot !== null;
        $isChannel = $group->type === 'channel';
        
        // Channels: any member can generate invite links
        // Groups: only admins/owners can generate invite links
        if ($isChannel) {
            abort_unless($isMember, 403, 'You must be a member to generate invite links.');
        } else {
            abort_unless($isAdmin, 403, 'Only group owners and admins can generate invite links.');
        }
        
        // For public channels, we don't need invite codes
        if ($group->type === 'channel' && $group->is_public) {
            return response()->json([
                'success' => false,
                'message' => 'Public channels do not need invite codes'
            ], 422);
        }

        $inviteCode = $group->generateInviteCode();
        $group->update(['invite_code' => $inviteCode]);

        $inviteLink = $group->getInviteLink();

        return response()->json([
            'success' => true,
            'message' => 'Invite link generated successfully',
            'invite_link' => $inviteLink,
            'invite_code' => $inviteCode
        ]);
    }

    /**
     * Get current invite link info
     * GET /groups/{id}/invite-info
     */
    public function getInviteInfo(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user = $request->user();
        
        // Check if user is a member
        abort_unless($group->isMember($user), 403, 'You must be a member to view invite info.');

        return response()->json([
            'success' => true,
            'has_invite_code' => !empty($group->invite_code),
            'invite_link' => $group->invite_code ? $group->getInviteLink() : null,
            'invite_code' => $group->invite_code,
            'group_type' => $group->type,
            'is_public' => $group->is_public
        ]);
    }

    /**
     * Mark all messages in a group as read for the current user
     * POST /groups/{id}/read
     */
    public function markAsRead(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user = $request->user();
        
        // Check if user is a member
        abort_unless($group->isMember($user), 403, 'You must be a member to mark messages as read.');
        
        try {
            // Capture the highest unread message id BEFORE marking read (so we can broadcast a single "read up to" receipt).
            $lastUnreadId = $group->messages()
                ->where('sender_id', '!=', $user->id)
                ->whereDoesntHave('statuses', function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->where('status', \App\Models\GroupMessageStatus::STATUS_READ);
                })
                ->max('id');

            // Mark all unread messages as read
            $group->markAllAsReadForUser($user->id);
            
            // Get updated unread count
            $unreadCount = $group->getUnreadCountForUser($user->id);

            // Broadcast read receipt if allowed by privacy settings.
            // We broadcast only the highest unread id to reduce event spam.
            if ($lastUnreadId && PrivacyService::shouldSendReadReceipt($user)) {
                try {
                    broadcast(new GroupMessageReadEvent($group->id, (int) $lastUnreadId, $user->id))->toOthers();
                } catch (\Throwable $e) {
                    Log::warning('Failed to broadcast GroupMessageReadEvent', [
                        'group_id' => $group->id,
                        'user_id' => $user->id,
                        'message_id' => (int) $lastUnreadId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Messages marked as read',
                'unread_count' => $unreadCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark group messages as read', [
                'group_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'message' => 'Failed to mark messages as read',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark the group as unread for the current user.
     * POST /groups/{id}/mark-unread
     */
    public function markUnread(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user = $request->user();
        abort_unless($group->isMember($user), 403, 'You must be a member to mark as unread.');

        $group->markAsUnreadForUser($user->id);
        $unreadCount = $group->getUnreadCountForUser($user->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Group marked as unread',
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Toggle admin approval requirement for joining the group.
     * PUT /groups/{id}/require-approval
     */
    public function toggleRequireApproval(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user = $request->user();
        
        // Only groups (not channels) support approval requirement
        if ($group->type === 'channel') {
            return response()->json([
                'success' => false,
                'message' => 'Channels do not support join approval.',
            ], 422);
        }
        
        // Check permissions - owner or admin can toggle
        $memberPivot = $group->members()->where('users.id', $user->id)->first()?->pivot;
        $userRole = $memberPivot?->role ?? 'member';
        $isOwner = $group->owner_id === $user->id;
        $isAdmin = $isOwner || $userRole === 'admin';
        
        abort_unless($isAdmin, 403, 'Only group owners and admins can change this setting.');
        
        $request->validate([
            'enabled' => 'required|boolean',
        ]);
        
        $group->update(['require_approval' => $request->boolean('enabled')]);
        
        return response()->json([
            'success' => true,
            'message' => $group->require_approval 
                ? 'Admin approval is now required to join this group.' 
                : 'Anyone with the invite link can now join this group.',
            'require_approval' => $group->require_approval,
        ]);
    }

    /**
     * Get pending join requests for a group (admins only).
     * GET /groups/{id}/join-requests
     */
    public function getJoinRequests(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user = $request->user();
        
        // Check permissions - owner or admin can view requests
        $memberPivot = $group->members()->where('users.id', $user->id)->first()?->pivot;
        $userRole = $memberPivot?->role ?? 'member';
        $isOwner = $group->owner_id === $user->id;
        $isAdmin = $isOwner || $userRole === 'admin';
        
        abort_unless($isAdmin, 403, 'Only group owners and admins can view join requests.');
        
        $status = $request->input('status', 'pending');
        
        $query = $group->joinRequests()->with(['user:id,name,phone,avatar_path']);
        
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        
        $requests = $query->latest()->paginate(20);
        
        return response()->json([
            'success' => true,
            'data' => $requests->map(function ($req) {
                return [
                    'id' => $req->id,
                    'user' => [
                        'id' => $req->user->id,
                        'name' => $req->user->name,
                        'phone' => $req->user->phone,
                        'avatar_url' => $req->user->avatar_path ? asset('storage/' . $req->user->avatar_path) : null,
                    ],
                    'message' => $req->message,
                    'status' => $req->status,
                    'created_at' => $req->created_at->toIso8601String(),
                    'reviewed_at' => $req->reviewed_at?->toIso8601String(),
                    'review_notes' => $req->review_notes,
                ];
            }),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Approve a join request.
     * POST /groups/{id}/join-requests/{requestId}/approve
     */
    public function approveJoinRequest(Request $request, $id, $requestId)
    {
        $group = Group::findOrFail($id);
        $user = $request->user();
        
        // Check permissions - owner or admin can approve
        $memberPivot = $group->members()->where('users.id', $user->id)->first()?->pivot;
        $userRole = $memberPivot?->role ?? 'member';
        $isOwner = $group->owner_id === $user->id;
        $isAdmin = $isOwner || $userRole === 'admin';
        
        abort_unless($isAdmin, 403, 'Only group owners and admins can approve join requests.');
        
        $joinRequest = GroupJoinRequest::where('id', $requestId)
            ->where('group_id', $group->id)
            ->where('status', GroupJoinRequest::STATUS_PENDING)
            ->firstOrFail();
        
        return DB::transaction(function () use ($group, $joinRequest, $user, $request) {
            // Check group member limit
            if (!$group->canAddMembers(1)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This group has reached its maximum member limit of ' . Group::MAX_GROUP_MEMBERS . '.',
                ], 422);
            }
            
            // Approve the request
            $joinRequest->approve($user->id, $request->input('notes'));
            
            // Add user to group
            $group->members()->syncWithoutDetaching([
                $joinRequest->user_id => [
                    'role' => 'member',
                    'joined_at' => now(),
                ],
            ]);
            
            // Create system message
            $group->createSystemMessage('joined', $joinRequest->user_id);
            
            return response()->json([
                'success' => true,
                'message' => 'Join request approved. User has been added to the group.',
            ]);
        });
    }

    /**
     * Reject a join request.
     * POST /groups/{id}/join-requests/{requestId}/reject
     */
    public function rejectJoinRequest(Request $request, $id, $requestId)
    {
        $group = Group::findOrFail($id);
        $user = $request->user();
        
        // Check permissions - owner or admin can reject
        $memberPivot = $group->members()->where('users.id', $user->id)->first()?->pivot;
        $userRole = $memberPivot?->role ?? 'member';
        $isOwner = $group->owner_id === $user->id;
        $isAdmin = $isOwner || $userRole === 'admin';
        
        abort_unless($isAdmin, 403, 'Only group owners and admins can reject join requests.');
        
        $joinRequest = GroupJoinRequest::where('id', $requestId)
            ->where('group_id', $group->id)
            ->where('status', GroupJoinRequest::STATUS_PENDING)
            ->firstOrFail();
        
        $joinRequest->reject($user->id, $request->input('notes'));
        
        return response()->json([
            'success' => true,
            'message' => 'Join request rejected.',
        ]);
    }

    /**
     * Batch approve/reject multiple join requests.
     * POST /groups/{id}/join-requests/batch
     */
    public function batchProcessJoinRequests(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user = $request->user();
        
        // Check permissions
        $memberPivot = $group->members()->where('users.id', $user->id)->first()?->pivot;
        $userRole = $memberPivot?->role ?? 'member';
        $isOwner = $group->owner_id === $user->id;
        $isAdmin = $isOwner || $userRole === 'admin';
        
        abort_unless($isAdmin, 403, 'Only group owners and admins can process join requests.');
        
        $request->validate([
            'action' => 'required|in:approve,reject',
            'request_ids' => 'required|array|min:1',
            'request_ids.*' => 'integer',
            'notes' => 'nullable|string|max:500',
        ]);
        
        $action = $request->input('action');
        $requestIds = $request->input('request_ids');
        $notes = $request->input('notes');
        
        $joinRequests = GroupJoinRequest::where('group_id', $group->id)
            ->whereIn('id', $requestIds)
            ->where('status', GroupJoinRequest::STATUS_PENDING)
            ->get();
        
        if ($joinRequests->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No pending requests found.',
            ], 404);
        }
        
        return DB::transaction(function () use ($group, $joinRequests, $user, $action, $notes) {
            $processed = 0;
            $skipped = 0;
            
            foreach ($joinRequests as $joinRequest) {
                if ($action === 'approve') {
                    // Check member limit for each approval
                    if (!$group->canAddMembers(1)) {
                        $skipped++;
                        continue;
                    }
                    
                    $joinRequest->approve($user->id, $notes);
                    
                    $group->members()->syncWithoutDetaching([
                        $joinRequest->user_id => [
                            'role' => 'member',
                            'joined_at' => now(),
                        ],
                    ]);
                    
                    $group->createSystemMessage('joined', $joinRequest->user_id);
                } else {
                    $joinRequest->reject($user->id, $notes);
                }
                
                $processed++;
            }
            
            $actionLabel = $action === 'approve' ? 'approved' : 'rejected';
            $message = "{$processed} request(s) {$actionLabel}.";
            if ($skipped > 0) {
                $message .= " {$skipped} skipped due to member limit.";
            }
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'processed' => $processed,
                'skipped' => $skipped,
            ]);
        });
    }

    /**
     * Cancel own join request (for the requesting user).
     * DELETE /groups/{id}/join-request
     */
    public function cancelJoinRequest(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user = $request->user();
        
        $joinRequest = GroupJoinRequest::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', GroupJoinRequest::STATUS_PENDING)
            ->first();
        
        if (!$joinRequest) {
            return response()->json([
                'success' => false,
                'message' => 'No pending join request found.',
            ], 404);
        }
        
        $joinRequest->cancel();
        
        return response()->json([
            'success' => true,
            'message' => 'Join request cancelled.',
        ]);
    }

    /**
     * Check if user has a pending join request for a group.
     * GET /groups/{id}/join-request-status
     */
    public function getJoinRequestStatus(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user = $request->user();
        
        // Check if already a member
        if ($group->isMember($user)) {
            return response()->json([
                'is_member' => true,
                'has_pending_request' => false,
                'request' => null,
            ]);
        }
        
        $joinRequest = GroupJoinRequest::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->latest()
            ->first();
        
        return response()->json([
            'is_member' => false,
            'has_pending_request' => $joinRequest && $joinRequest->isPending(),
            'require_approval' => $group->require_approval ?? false,
            'request' => $joinRequest ? [
                'id' => $joinRequest->id,
                'status' => $joinRequest->status,
                'message' => $joinRequest->message,
                'created_at' => $joinRequest->created_at->toIso8601String(),
                'reviewed_at' => $joinRequest->reviewed_at?->toIso8601String(),
                'review_notes' => $joinRequest->review_notes,
            ] : null,
        ]);
    }
}
