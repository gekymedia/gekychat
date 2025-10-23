<?php

namespace App\Http\Controllers;

use App\Events\GroupMessageDeleted;
use App\Events\GroupMessageEdited;
use App\Events\GroupMessageSent;
use App\Events\GroupMessageReadEvent;
use App\Events\GroupUpdated;
use App\Events\TypingInGroup;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\GroupMessageReaction;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    public function sendMessage(Request $request, Group $group)
    {
        $this->authorize('send-group-message', $group);

        $data = $request->validate([
            'body'            => ['nullable', 'string', 'max:5000'],
            'reply_to'        => ['nullable', 'integer', 'exists:group_messages,id'],
            'forward_from_id' => ['nullable', 'integer', 'exists:group_messages,id'],
            'attachments'     => ['nullable', 'array'],
            'attachments.*'   => ['file', 'mimes:jpg,jpeg,png,gif,webp,pdf,zip,doc,docx,mp4,mp3,mov,wav', 'max:10240'],
        ]);

        if (empty($data['body']) && !$request->hasFile('attachments') && !$request->filled('forward_from_id')) {
            return response()->json(['status' => 'error', 'message' => 'Please enter a message, attach a file, or forward a message.'], 422);
        }

        $forwardChain = null;
        if (!empty($data['forward_from_id'])) {
            $originalMessage = GroupMessage::with('sender')->find($data['forward_from_id']);
            $forwardChain = $originalMessage ? $originalMessage->buildForwardChain() : null;
        }

        $message = $group->messages()->create([
            'sender_id'         => auth()->id(),
            'body'              => $data['body'] ?? '',
            'reply_to'          => $data['reply_to'] ?? null,
            'forwarded_from_id' => $data['forward_from_id'] ?? null,
            'forward_chain'     => $forwardChain,
            'delivered_at'      => now(),
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if (!$file) continue;
                $path = $file->store('attachments', 'public');
                $message->attachments()->create([
                    'user_id'       => auth()->id(),
                    'file_path'     => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type'     => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                ]);
            }
        }

        $message->load(['sender', 'attachments', 'replyTo', 'forwardedFrom', 'reactions.user']);

        // ✅ FIXED: Use updated GroupMessageSent event
        broadcast(new GroupMessageSent($message))->toOthers();

        return response()->json(['status' => 'ok', 'message' => $message]);
    }

    /**
     * Promote a member to admin
     */
    public function promoteMember(Request $request, Group $group, $userId)
    {
        Gate::authorize('manage-group', $group);

        $user = User::findOrFail($userId);

        // Check if user is a member of the group
        if (!$group->members()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a member of this group'
            ], 404);
        }

        // Don't allow promoting the owner
        if ($user->id === $group->owner_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change owner role'
            ], 422);
        }

        $group->members()->updateExistingPivot($user->id, [
            'role' => 'admin'
        ]);

        // ✅ FIXED: Broadcast group update
        broadcast(new GroupUpdated(
            $group,
            'member_promoted',
            ['user_id' => $user->id, 'new_role' => 'admin'],
            auth()->id()
        ))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Member promoted to admin'
        ]);
    }

    /**
     * Remove a member from group
     */
    public function removeMember(Group $group, $userId)
    {
        Gate::authorize('manage-group', $group);

        $user = User::findOrFail($userId);

        // Don't allow removing owner
        if ($user->id === $group->owner_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove group owner'
            ], 422);
        }

        // Don't allow removing yourself if you're not owner
        if ($user->id === auth()->id() && $group->owner_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only remove yourself as group owner'
            ], 422);
        }

        $group->members()->detach($user->id);

        // ✅ FIXED: Broadcast group update
        broadcast(new GroupUpdated(
            $group,
            'member_removed',
            ['user_id' => $user->id, 'user_name' => $user->name ?? $user->phone],
            auth()->id()
        ))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Member removed from group'
        ]);
    }

    public function history(Request $request, Group $group)
    {
        Gate::authorize('view-group', $group);

        $messages = $group->messages()
            ->visibleTo(auth()->id())
            ->with(['sender:id,name,phone,avatar_path', 'attachments', 'replyTo', 'reactions.user:id,name,avatar_path'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['messages' => $messages]);
    }

    public function forwardGroupMessage(Request $request, Group $group)
    {
        Gate::authorize('view-group', $group);

        $data = $request->validate([
            'message_id'  => 'required|exists:group_messages,id',
            'group_ids'   => 'required|array',
            'group_ids.*' => 'exists:groups,id'
        ]);

        $originalMessage = GroupMessage::with(['sender', 'attachments'])->find($data['message_id']);
        $forwardChain    = $originalMessage ? $originalMessage->buildForwardChain() : null;

        $forwardedMessages = [];

        foreach ($data['group_ids'] as $groupId) {
            $targetGroup = Group::find($groupId);
            if (!$targetGroup || !$targetGroup->members()->where('user_id', auth()->id())->exists()) continue;

            $message = $targetGroup->messages()->create([
                'sender_id'         => auth()->id(),
                'body'              => $originalMessage?->body ?? '',
                'forwarded_from_id' => $originalMessage?->id,
                'forward_chain'     => $forwardChain,
                'delivered_at'      => now()
            ]);

            if ($originalMessage && $originalMessage->attachments->isNotEmpty()) {
                foreach ($originalMessage->attachments as $attachment) {
                    $ext     = pathinfo($attachment->file_path, PATHINFO_EXTENSION);
                    $newPath = 'attachments/' . uniqid('fwd_', true) . ($ext ? ('.' . $ext) : '');
                    Storage::disk('public')->copy($attachment->file_path, $newPath);

                    $message->attachments()->create([
                        'user_id'       => auth()->id(),
                        'file_path'     => $newPath,
                        'original_name' => $attachment->original_name,
                        'mime_type'     => $attachment->mime_type,
                        'size'          => $attachment->size
                    ]);
                }
            }

            $message->load(['sender', 'attachments', 'forwardedFrom', 'reactions.user']);

            // ✅ FIXED: Use GroupMessageSent event
            broadcast(new GroupMessageSent($message))->toOthers();

            $forwardedMessages[] = $message;
        }

        return response()->json([
            'status'   => 'success',
            'messages' => $forwardedMessages
        ]);
    }

    public function create()
    {
        $users = User::where('id', '!=', auth()->id())
            ->orderByRaw('COALESCE(NULLIF(name, ""), phone)')
            ->get(['id', 'name', 'phone', 'avatar_path']);

        return view('groups.create', compact('users'));
    }

    /**
     * Show the form for editing the specified group.
     */
    // public function edit(Group $group)
    // {
    //     Gate::authorize('manage-group', $group);

    //     $group->load(['members']);

    //     return view('groups.edit', compact('group'));
    // }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:64',
            'description' => 'nullable|string|max:200',
            'avatar'      => 'nullable|image|max:2048',
            'members'     => 'nullable|array',
            'members.*'   => 'exists:users,id',
            'type'        => 'required|in:channel,group',
            'is_public'   => 'boolean',
        ]);

        return DB::transaction(function () use ($request, $data) {
            // Determine if public based on type
            $isPublic = $data['type'] === 'channel' ? true : ($data['is_public'] ?? false);

            $group = Group::create([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'owner_id'    => auth()->id(),
                'type'        => $data['type'],
                'is_public'   => $isPublic,
            ]);

            if ($request->hasFile('avatar')) {
                $path = $request->file('avatar')->store('group-avatars', 'public');
                $group->update(['avatar_path' => $path]);
            }

            $group->members()->attach(auth()->id(), ['role' => 'admin', 'joined_at' => now()]);

            if (!empty($data['members'])) {
                $memberIds = collect($data['members'])
                    ->filter(fn($id) => (int)$id !== (int)auth()->id())
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

            return redirect()
                ->route('groups.show', $group)
                ->with('status', $group->type === 'channel' ? 'Channel created' : 'Group created');
        });
    }

    public function show(Group $group)
    {
        Gate::authorize('view-group', $group);

        $group->load([
            'members:id,name,phone,avatar_path',
            'messages' => fn($q) => $q->with([
                'sender:id,name,phone,avatar_path',
                'attachments',
                'replyTo',
                'reactions.user:id,name,avatar_path',
            ])
                ->visibleTo(auth()->id())
                ->orderBy('created_at', 'asc'),
        ]);

        $this->markMessagesAsRead($group);

        $users = User::where('id', '!=', auth()->id())
            ->orderByRaw('COALESCE(NULLIF(name, ""), phone)')
            ->get(['id', 'name', 'phone', 'avatar_path']);

        $userId = auth()->id();

        // Pivot-based DM list
        $conversations = Conversation::with([
            'members:id,name,phone,avatar_path',
            'lastMessage',
        ])
            ->where('is_group', false)
            ->whereHas('members', fn($q) => $q->whereKey($userId))
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->get();

        $groups = Group::with([
            'members:id',
            'messages' => function ($q) {
                $q->with('sender:id,name,phone,avatar_path')->latest()->limit(1);
            },
        ])
            ->whereHas('members', fn($q) => $q->where('users.id', $userId))
            ->orderByDesc(
                GroupMessage::select('created_at')
                    ->whereColumn('group_messages.group_id', 'groups.id')
                    ->latest()
                    ->take(1)
            )
            ->get();

        return view('groups.index', [
            'group'          => $group,
            'messages'       => $group->messages,
            'conversations'  => $conversations,
            'groups'         => $groups,
            'users'          => $users,
            'botConversation' => null,
        ]);
    }

    protected function markMessagesAsRead(Group $group)
    {
        $unread = $group->messages()
            ->where('sender_id', '!=', auth()->id())
            ->whereNull('read_at')
            ->pluck('id');

        if ($unread->isNotEmpty()) {
            $group->messages()->whereIn('id', $unread)->update(['read_at' => now()]);

            // ✅ FIXED: Broadcast read receipts for each message
            foreach ($unread as $messageId) {
                broadcast(new GroupMessageReadEvent(
                    $group->id,
                    $messageId,
                    auth()->id()
                ))->toOthers();
            }
        }
    }

    public function editMessage(Request $request, Group $group, GroupMessage $message)
    {
        Gate::authorize('edit-message', $message);

        $data = $request->validate([
            'body' => 'required|string|max:4000',
        ]);

        $message->update([
            'body'      => $data['body'],
            'edited_at' => now(),
        ]);

        $message->load([
            'sender:id,name,phone,avatar_path',
            'attachments',
            'replyTo',
            'reactions.user:id,name,avatar_path'
        ]);

        // ✅ FIXED: Use GroupMessageEdited event
        broadcast(new GroupMessageEdited($message))->toOthers();

        return response()->json([
            'status'  => 'success',
            'message' => $message,
        ]);
    }

    public function addReaction(Request $request, Group $group, GroupMessage $message)
    {
        Gate::authorize('view-group', $group);

        $data = $request->validate([
            'emoji' => 'required|string|max:4',
        ]);

        $existing = GroupMessageReaction::where([
            'user_id'          => auth()->id(),
            'group_message_id' => $message->id,
            'emoji'            => $data['emoji'],
        ])->first();

        if ($existing) {
            $existing->delete();
            $action = 'removed';
        } else {
            GroupMessageReaction::where([
                'user_id'          => auth()->id(),
                'group_message_id' => $message->id,
            ])->delete();

            $reaction = $message->reactions()->create([
                'user_id' => auth()->id(),
                'emoji'   => $data['emoji'],
            ]);
            $reaction->load('user:id,name,avatar_path');
            $action = 'added';
        }

        $message->load('reactions.user:id,name,avatar_path');

        // ✅ FIXED: Use GroupMessageEdited event for reaction updates
        broadcast(new GroupMessageEdited($message))->toOthers();

        return response()->json([
            'status'  => 'success',
            'action'  => $action,
            'message' => $message,
        ]);
    }

    public function typing(Request $request, Group $group)
    {
        Gate::authorize('view-group', $group);

        $data = $request->validate([
            'is_typing' => 'required|boolean',
        ]);

        $user = $request->user();

        // ✅ FIXED: Use TypingInGroup event
        broadcast(new TypingInGroup(
            $group->id,
            auth()->id(),
            (bool) $data['is_typing']
        ))->toOthers();

        return response()->json(['status' => 'success']);
    }

    public function addMembers(Request $request, Group $group)
    {
        Gate::authorize('manage-group', $group);

        $data = $request->validate([
            'members'   => 'required|array|min:1',
            'members.*' => 'exists:users,id',
        ]);

        $memberIds = collect($data['members'])
            ->filter(fn($id) => (int) $id !== (int) $group->owner_id)
            ->unique()
            ->values();

        if ($memberIds->isEmpty()) {
            return back()->withErrors(['members' => 'No valid members to add.']);
        }

        $attach = [];
        foreach ($memberIds as $uid) {
            $attach[$uid] = ['role' => 'member', 'joined_at' => now()];
        }

        $group->members()->syncWithoutDetaching($attach);

        // ✅ FIXED: Broadcast group update
        broadcast(new GroupUpdated(
            $group,
            'members_added',
            ['member_ids' => $memberIds->toArray(), 'count' => $memberIds->count()],
            auth()->id()
        ))->toOthers();

        return back()->with('status', 'Members added.');
    }
 public function updateGroup(Request $request, Group $group)
{
    Gate::authorize('manage-group', $group);

    $data = $request->validate([
        'name'        => 'required|string|max:64',
        'description' => 'nullable|string|max:200',
        'avatar'      => 'nullable|image|max:2048',
        'type'        => 'required|in:channel,group',
        'is_public'   => 'boolean',
    ]);

    $isPublic = $data['type'] === 'channel' ? true : ($data['is_public'] ?? $group->is_public);

    $oldData = [
        'name' => $group->name,
        'description' => $group->description,
        'type' => $group->type,
        'is_public' => $group->is_public,
    ];

    $group->update([
        'name'        => $data['name'],
        'description' => $data['description'] ?? null,
        'type'        => $data['type'],
        'is_public'   => $isPublic,
    ]);

    if ($request->hasFile('avatar')) {
        if ($group->avatar_path) {
            Storage::disk('public')->delete($group->avatar_path);
        }
        $path = $request->file('avatar')->store('group-avatars', 'public');
        $group->update(['avatar_path' => $path]);
    }

    // Refresh the group to get updated relationships
    $group->refresh();

    // Broadcast group update
    broadcast(new GroupUpdated(
        $group,
        'info_updated',
        [
            'old_data' => $oldData,
            'new_data' => [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'is_public' => $isPublic,
            ]
        ],
        auth()->id()
    ))->toOthers();

    // Return JSON response for AJAX requests
    if ($request->expectsJson()) {
        return response()->json([
            'success' => true,
            'message' => $group->type === 'channel' ? 'Channel updated successfully' : 'Group updated successfully',
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'avatar_path' => $group->avatar_path,
                'type' => $group->type,
                'is_public' => $group->is_public,
            ]
        ]);
    }

    return back()->with('status', $group->type === 'channel' ? 'Channel updated' : 'Group updated');
}
/**
 * Show the form for editing the specified group.
 */
public function edit(Group $group)
{
    Gate::authorize('manage-group', $group);

    $group->load(['members']);

    // Return JSON for AJAX requests or view for regular requests
    if (request()->expectsJson()) {
        return response()->json([
            'group' => $group,
            'members' => $group->members
        ]);
    }

    return view('groups.edit', compact('group'));
}
    public function leave(Group $group)
    {
        Gate::authorize('view-group', $group);

        if ((int) $group->owner_id === (int) auth()->id()) {
            return back()->withErrors(['leave' => 'Owner cannot leave. Transfer ownership or delete group.']);
        }

        $group->members()->detach(auth()->id());

        // ✅ FIXED: Broadcast group update
        broadcast(new GroupUpdated(
            $group,
            'member_left',
            ['user_id' => auth()->id(), 'user_name' => auth()->user()->name ?? auth()->user()->phone],
            auth()->id()
        ))->toOthers();

        return redirect()->route('chat.index')->with('status', 'You left the ' . ($group->type === 'channel' ? 'channel' : 'group') . '.');
    }

    public function transferOwnership(Request $request, Group $group)
    {
        Gate::authorize('transfer-ownership', $group);

        $data = $request->validate([
            'new_owner_id' => 'required|exists:users,id',
        ]);

        if ((int) $data['new_owner_id'] === (int) auth()->id()) {
            return back()->withErrors(['new_owner_id' => 'You are already the owner.']);
        }

        if (!$group->members()->where('user_id', $data['new_owner_id'])->exists()) {
            return back()->withErrors(['new_owner_id' => 'User is not a member of this ' . ($group->type === 'channel' ? 'channel' : 'group') . '.']);
        }

        $oldOwnerId = $group->owner_id;

        DB::transaction(function () use ($group, $data) {
            $group->update(['owner_id' => $data['new_owner_id']]);
            $group->members()->updateExistingPivot($data['new_owner_id'], ['role' => 'admin']);
        });

        // ✅ FIXED: Broadcast group update
        broadcast(new GroupUpdated(
            $group,
            'ownership_transferred',
            [
                'old_owner_id' => $oldOwnerId,
                'new_owner_id' => $data['new_owner_id']
            ],
            auth()->id()
        ))->toOthers();

        return back()->with('status', 'Ownership transferred successfully.');
    }

    public function deleteMessage(Group $group, GroupMessage $message)
    {
        if ($message->sender_id !== auth()->id() && !$group->isAdmin(auth()->id())) {
            abort(403);
        }

        // ✅ FIXED: Use the proper method to mark as deleted for user
        $message->deleteForUser(auth()->id());

        // ✅ FIXED: Use GroupMessageDeleted event
        broadcast(new GroupMessageDeleted($group->id, $message->id, auth()->id()))
            ->toOthers();

        return response()->json(['success' => true]);
    }

    public function getMessages(Group $group)
    {
        Gate::authorize('view-group', $group);

        $messages = $group->messages()
            ->visibleTo(auth()->id())
            ->with(['sender', 'attachments', 'reactions.user', 'replyTo', 'forwardedFrom'])
            ->latest()
            ->paginate(20);

        return response()->json($messages);
    }

    /**
     * Forward a group message to mixed targets (groups and/or conversations).
     */
    public function forwardToTargets(Request $request)
    {
        $data = $request->validate([
            'message_id'        => 'required|exists:group_messages,id',
            'targets'           => 'required|array|min:1',
            'targets.*.type'    => 'required|in:group,conversation',
            'targets.*.id'      => 'required|integer',
        ]);

        $original = GroupMessage::with(['sender', 'attachments', 'group.members'])->findOrFail($data['message_id']);

        if (!$original->group || !$original->group->members->contains('id', auth()->id())) {
            abort(403);
        }

        // Generic chain for cross-type
        $baseChain = $original->forward_chain ?? [];
        array_unshift($baseChain, [
            'id'           => $original->id,
            'sender'       => $original->sender->name ?? $original->sender->phone ?? 'User',
            'body'         => Str::limit($original->body ?? '', 100),
            'timestamp'    => optional($original->created_at)->toISOString(),
            'is_encrypted' => false,
            'source'       => 'group',
        ]);

        $results = ['groups' => [], 'conversations' => []];

        foreach ($data['targets'] as $target) {
            if ($target['type'] === 'group') {
                $targetGroup = Group::find($target['id']);
                if (!$targetGroup || !$targetGroup->members()->where('user_id', auth()->id())->exists()) continue;

                $forwardChain = $original->buildForwardChain();

                $msg = $targetGroup->messages()->create([
                    'sender_id'         => auth()->id(),
                    'body'              => $original->body ?? '',
                    'forwarded_from_id' => $original->id,
                    'forward_chain'     => $forwardChain,
                    'delivered_at'      => now(),
                ]);

                if ($original->attachments->isNotEmpty()) {
                    foreach ($original->attachments as $attachment) {
                        $ext     = pathinfo($attachment->file_path, PATHINFO_EXTENSION);
                        $newPath = 'attachments/' . uniqid('fwd_', true) . ($ext ? ('.' . $ext) : '');
                        Storage::disk('public')->copy($attachment->file_path, $newPath);

                        $msg->attachments()->create([
                            'user_id'       => auth()->id(),
                            'file_path'     => $newPath,
                            'original_name' => $attachment->original_name,
                            'mime_type'     => $attachment->mime_type,
                            'size'          => $attachment->size,
                        ]);
                    }
                }

                $msg->load(['sender', 'attachments', 'forwardedFrom', 'reactions.user']);

                // ✅ FIXED: Use GroupMessageSent event
                broadcast(new GroupMessageSent($msg))->toOthers();

                $results['groups'][] = $msg;
            } else {
                // Pivot-based membership check for DM target
                $conversation = Conversation::find($target['id']);
                if (!$conversation || !$conversation->members()->whereKey(auth()->id())->exists()) {
                    continue;
                }

                $m = Message::create([
                    'conversation_id'   => $conversation->id,
                    'sender_id'         => auth()->id(),
                    'body'              => $original->body ?? '',
                    'forwarded_from_id' => null,
                    'forward_chain'     => $baseChain,
                ]);

                if ($original->attachments->isNotEmpty()) {
                    foreach ($original->attachments as $attachment) {
                        $ext     = pathinfo($attachment->file_path, PATHINFO_EXTENSION);
                        $newPath = 'attachments/' . uniqid('fwd_', true) . ($ext ? ('.' . $ext) : '');
                        Storage::disk('public')->copy($attachment->file_path, $newPath);

                        $m->attachments()->create([
                            'user_id'       => auth()->id(),
                            'file_path'     => $newPath,
                            'original_name' => $attachment->original_name,
                            'mime_type'     => $attachment->mime_type,
                            'size'          => $attachment->size,
                        ]);
                    }
                }

                $m->load(['sender', 'attachments', 'reactions.user']);

                // ✅ FIXED: Use MessageSent event for DM
                broadcast(new \App\Events\MessageSent($m))->toOthers();

                $results['conversations'][] = $m;
            }
        }

        return response()->json([
            'status'  => 'success',
            'results' => $results,
        ]);
    }

    /**
     * Join a group via invite code
     */
    public function join($inviteCode)
    {
        $group = Group::where('invite_code', $inviteCode)->firstOrFail();

        // Check if user is already a member
        if ($group->isMember(auth()->id())) {
            return redirect()->route('groups.show', $group)
                ->with('status', 'You are already a member of this ' . ($group->type === 'channel' ? 'channel' : 'group'));
        }

        // Add user as member
        $group->members()->attach(auth()->id(), [
            'role' => 'member',
            'joined_at' => now()
        ]);

        // ✅ FIXED: Broadcast group update
        broadcast(new GroupUpdated(
            $group,
            'member_joined',
            ['user_id' => auth()->id(), 'user_name' => auth()->user()->name ?? auth()->user()->phone],
            auth()->id()
        ))->toOthers();

        return redirect()->route('groups.show', $group)
            ->with('status', 'You have joined the ' . ($group->type === 'channel' ? 'channel' : 'group'));
    }

    /**
     * Generate new invite code for private group
     */
    public function generateInvite(Request $request, Group $group)
    {
        Gate::authorize('manage-group', $group);

        // Only private groups need invite codes
        if ($group->type === 'channel' && $group->is_public) {
            return back()->withErrors(['invite' => 'Public channels do not need invite codes.']);
        }

        $group->update([
            'invite_code' => Str::random(10)
        ]);

        return back()->with('status', 'New invite code generated: ' . $group->invite_code);
    }
}
