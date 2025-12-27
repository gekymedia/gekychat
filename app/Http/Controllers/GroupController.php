<?php

namespace App\Http\Controllers;

use App\Events\GroupMessageDeleted;
use App\Events\GroupMessageEdited;
use App\Events\GroupMessageSent;
use App\Events\GroupMessageReadEvent;
use App\Events\GroupTyping;
use App\Events\GroupUpdated;
use App\Events\TypingInGroup;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\GroupMessageReaction;
use App\Models\GroupMessageStatus;
use App\Models\Message;
use App\Models\User;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    /** @var \App\Models\User $user */
    public function sendMessage(Request $request, Group $group)
    {
        $this->authorize('send-group-message', $group);

        $data = $request->validate([
            'body'            => ['nullable', 'string', 'max:5000'],
            'reply_to'        => ['nullable', 'integer', 'exists:group_messages,id'],
            'forward_from_id' => ['nullable', 'integer', 'exists:group_messages,id'],
            'attachments'     => ['nullable', 'array'],
            'attachments.*'   => ['file', 'mimes:jpg,jpeg,png,gif,webp,pdf,zip,doc,docx,mp4,mp3,mov,wav,webm,ogg', 'max:10240'],
        ]);

        if (empty($data['body']) && !$request->hasFile('attachments') && !$request->filled('forward_from_id')) {
            return response()->json(['status' => 'error', 'message' => 'Please enter a message, attach a file, or forward a message.'], 422);
        }

        $forwardChain = null;
        $originalMessage = null;
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
            'location_data'     => $originalMessage?->location_data,
            'contact_data'      => $originalMessage?->contact_data,
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

        if (!$group->members()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a member of this group'
            ], 404);
        }

        if ($user->id === $group->owner_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change owner role'
            ], 422);
        }

        $group->members()->updateExistingPivot($user->id, [
            'role' => 'admin'
        ]);

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

        if ($user->id === $group->owner_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove group owner'
            ], 422);
        }

        if ($user->id === auth()->id() && $group->owner_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only remove yourself as group owner'
            ], 422);
        }

        $group->members()->detach($user->id);

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
            ->orderBy('created_at', 'asc')
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
                'location_data'     => $originalMessage?->location_data,
                'contact_data'      => $originalMessage?->contact_data,
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
            $isPublic = $data['type'] === 'channel' ? true : ($data['is_public'] ?? false);

            $group = Group::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'owner_id' => auth()->id(),
                'type' => $data['type'],
                'is_public' => $isPublic,
                'invite_code' => $isPublic ? null : Str::random(10),
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
        
        $groupMembers = $group->members;
        $securitySettings = [
            'isEncrypted' => false,
            'expiresIn' => '',
        ];
        
        // Check if user can send messages (for channels, only admins/owners can send)
        $canSendMessages = Gate::allows('send-group-message', $group);
        
        return view('groups.index', [
            'group'          => $group,
            'messages'       => $group->messages,
            'conversations'  => $conversations,
            'groups'         => $groups,
            'users'          => $users,
            'botConversation' => null,
            'membersData'   => $groupMembers,
            'securitySettings' => $securitySettings,
            'canSendMessages' => $canSendMessages,
        ]);
    }

 protected function markMessagesAsRead(Group $group)
{
    $userId = auth()->id();

    $unreadMessages = $group->messages()
        ->where('sender_id', '!=', $userId)
        ->whereDoesntHave('readers', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->get();

    if ($unreadMessages->isEmpty()) {
        return;
    }

    foreach ($unreadMessages as $message) {
        // Use updateOrCreate on statuses relationship instead of syncWithoutDetaching
        $message->statuses()->updateOrCreate(
            ['user_id' => $userId],
            [
                'status' => GroupMessageStatus::STATUS_READ,
                'updated_at' => now(),
            ]
        );

        broadcast(new GroupMessageReadEvent(
            $group->id,
            $message->id,
            $userId
        ))->toOthers();
    }
}


    public function markAsRead(Group $group)
    {
        if ($group->isMember(auth()->id())) {
            $unreadMessages = $group->messages()
                ->where('sender_id', '!=', auth()->id())
                ->whereDoesntHave('readers', function ($query) {
                    $query->where('user_id', auth()->id());
                })
                ->get();

            foreach ($unreadMessages as $message) {
                // Use updateOrCreate on statuses relationship instead of syncWithoutDetaching
                $message->statuses()->updateOrCreate(
                    ['user_id' => auth()->id()],
                    [
                        'status' => GroupMessageStatus::STATUS_READ,
                        'updated_at' => now(),
                    ]
                );
                
                broadcast(new GroupMessageReadEvent(
                    $group->id,
                    $message->id,
                    auth()->id()
                ))->toOthers();
            }
            
            return response()->json(['success' => true, 'marked_read' => $unreadMessages->count()]);
        }

        return response()->json(['error' => 'Not a member'], 403);
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

        // Handle DELETE method (stop typing) - is_typing can come from query params
        if ($request->isMethod('DELETE')) {
            $isTyping = false;
        } else {
            // Handle POST method - validate request body
            $data = $request->validate([
                'is_typing' => 'required|boolean',
            ]);
            $isTyping = (bool) $data['is_typing'];
        }

        // Ensure we get a User model, not just an ID
        $user = $request->user();
        if (!$user instanceof User) {
            // Fallback: get user by ID if user() returned an ID instead of model
            $userId = is_int($user) ? $user : auth()->id();
            $user = User::findOrFail($userId);
        }

        broadcast(new GroupTyping(
            $group->id,
            $user,
            $isTyping
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
        
        // Reload group to get fresh member count
        $group->refresh();
        $addedBy = auth()->user();

        // Broadcast to each added member individually on their user channel for sidebar update
        foreach ($memberIds as $userId) {
            event(new \App\Events\UserAddedToGroup(
                $group,
                $addedBy,
                $userId
            ));
        }

        // Broadcast to existing group members about new additions
        broadcast(new GroupUpdated(
            $group,
            'members_added',
            [
                'member_ids' => $memberIds->toArray(), 
                'count' => $memberIds->count(),
                'added_by' => [
                    'id' => $addedBy->id,
                    'name' => $addedBy->name ?? $addedBy->phone,
                ]
            ],
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

        $group->refresh();

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

        $message->deleteForUser(auth()->id());

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
                    'location_data'     => $original->location_data,
                    'contact_data'      => $original->contact_data,
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

                broadcast(new GroupMessageSent($msg))->toOthers();

                $results['groups'][] = $msg;
            } else {
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
                    'location_data'     => $original->location_data,
                    'contact_data'      => $original->contact_data,
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

        if ($group->isMember(auth()->id())) {
            return redirect()->route('groups.show', $group)
                ->with('status', 'You are already a member of this ' . ($group->type === 'channel' ? 'channel' : 'group'));
        }

        $group->members()->attach(auth()->id(), [
            'role' => 'member',
            'joined_at' => now()
        ]);

        broadcast(new GroupUpdated(
            $group,
            'member_joined',
            ['user_id' => auth()->id(), 'user_name' => auth()->user()->name ?? auth()->user()->phone],
            auth()->id()
        ))->toOthers();

        return redirect()->route('groups.show', $group)
            ->with('status', 'You have joined the ' . ($group->type === 'channel' ? 'channel' : 'group'));
    }

    public function getMembers(Group $group)
    {
        $this->ensureMember($group);

        $members = $group->members()
            ->select('id', 'name', 'phone', 'avatar_path')
            ->get()
            ->map(function ($member) use ($group) {
                $pivot = $member->pivot;
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'phone' => $member->phone,
                    'avatar_path' => $member->avatar_path,
                    'role' => $pivot->role,
                    'is_online' => $member->is_online,
                ];
            });

        return response()->json([
            'success' => true,
            'members' => $members
        ]);
    }

    /**
     * Ensure user is a member of the group
     */
    private function ensureMember(Group $group)
    {
        if (!$group->members()->where('user_id', auth()->id())->exists()) {
            abort(403, 'You are not a member of this group');
        }
    }

    /**
     * Generate or regenerate invite link for group
     */
    public function generateInvite(Request $request, Group $group)
    {
        Gate::authorize('manage-group', $group);

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
     * Join a group via invite link
     */
    public function joinViaInvite(Request $request, $inviteCode)
    {
        // Allow joining via invite link without authorization checks
        // This is a public action - anyone with the invite code can join
        $group = Group::where('invite_code', $inviteCode)->firstOrFail();
        
        $user = auth()->user();
        
        // Ensure user is authenticated (middleware should handle this, but double-check)
        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must be logged in to join a group'
                ], 401);
            }
            return redirect()->route('login')->with('error', 'Please log in to join this group');
        }

        // Check if user is already a member
        if ($group->isMember($user->id)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are already a member of this ' . ($group->type === 'channel' ? 'channel' : 'group')
                ], 422);
            }
            
            return redirect()->route('groups.show', $group)
                ->with('status', 'You are already a member of this ' . ($group->type === 'channel' ? 'channel' : 'group'));
        }

        // Add user to group
        $group->members()->attach($user->id, [
            'role' => 'member',
            'joined_at' => now()
        ]);

        // Broadcast the update
        broadcast(new GroupUpdated(
            $group,
            'member_joined',
            [
                'user_id' => $user->id,
                'user_name' => $user->name ?? $user->phone,
                'via_invite' => true
            ],
            $user->id
        ))->toOthers();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'You have successfully joined the ' . ($group->type === 'channel' ? 'channel' : 'group'),
                'group' => $group->load('members')
            ]);
        }

        return redirect()->route('groups.show', $group)
            ->with('status', 'You have joined the ' . ($group->type === 'channel' ? 'channel' : 'group'));
    }

    /**
     * Get current invite link info
     */
    public function getInviteInfo(Group $group)
    {
        Gate::authorize('view-group', $group);

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
     * Revoke invite link
     */
    public function revokeInvite(Request $request, Group $group)
    {
        Gate::authorize('manage-group', $group);

        $group->update(['invite_code' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Invite link revoked successfully'
        ]);
    }

    /**
     * Share invite link via different methods
     */
    public function shareInvite(Request $request, Group $group)
    {
        Gate::authorize('view-group', $group);

        $data = $request->validate([
            'method' => 'required|in:copy,whatsapp,telegram,email,sms',
            'recipients' => 'nullable|array',
            'recipients.*' => 'email|phone'
        ]);

        $inviteLink = $group->invite_code ? $group->getInviteLink() : null;
        
        if (!$inviteLink) {
            return response()->json([
                'success' => false,
                'message' => 'No active invite link found. Please generate one first.'
            ], 422);
        }

        $message = "Join our " . ($group->type === 'channel' ? 'channel' : 'group') . " \"{$group->name}\" on our platform: {$inviteLink}";

        switch ($data['method']) {
            case 'copy':
                return response()->json([
                    'success' => true,
                    'message' => 'Invite link copied to clipboard',
                    'invite_link' => $inviteLink
                ]);

            case 'whatsapp':
                $whatsappUrl = "https://wa.me/?text=" . urlencode($message);
                return response()->json([
                    'success' => true,
                    'message' => 'Opening WhatsApp...',
                    'share_url' => $whatsappUrl
                ]);

            case 'telegram':
                $telegramUrl = "https://t.me/share/url?url=" . urlencode($inviteLink) . "&text=" . urlencode($group->name);
                return response()->json([
                    'success' => true,
                    'message' => 'Opening Telegram...',
                    'share_url' => $telegramUrl
                ]);

            case 'email':
                // You'll need to implement email sending logic here
                return response()->json([
                    'success' => true,
                    'message' => 'Email sharing functionality to be implemented'
                ]);

            case 'sms':
                // You'll need to implement SMS sending logic here
                return response()->json([
                    'success' => true,
                    'message' => 'SMS sharing functionality to be implemented'
                ]);
        }
    }


// Add these methods to your existing GroupController

/**
 * Share location in group
 */
public function shareLocation(Request $request, Group $group)
{
    $this->authorize('send-group-message', $group);

    $request->validate([
        'latitude' => 'required|numeric|between:-90,90',
        'longitude' => 'required|numeric|between:-180,180',
        'address' => 'nullable|string|max:500',
        'place_name' => 'nullable|string|max:255',
    ]);

    $locationData = [
        'type' => 'location',
        'latitude' => $request->latitude,
        'longitude' => $request->longitude,
        'address' => $request->address,
        'place_name' => $request->place_name,
        'shared_at' => now()->toISOString(),
    ];

    $message = $group->messages()->create([
        'sender_id' => auth()->id(),
        'body' => '📍 Shared location',
        'location_data' => $locationData,
        'delivered_at' => now(),
    ]);

    $message->load(['sender', 'attachments', 'reactions.user']);

    broadcast(new GroupMessageSent($message))->toOthers();

    return response()->json([
        'status' => 'success',
        'message' => $message,
    ]);
}

/**
 * Share contact in group
 */
public function shareContact(Request $request, Group $group)
{
    $this->authorize('send-group-message', $group);

    $request->validate([
        'contact_id' => 'required|exists:contacts,id',
    ]);

    $contact = Contact::where('id', $request->contact_id)
        ->where('user_id', auth()->id())
        ->firstOrFail();

    $contactUser = User::where('phone', $contact->phone)->first();

    $contactData = [
        'type' => 'contact',
        'contact_id' => $contact->id,
        'display_name' => $contact->display_name ?? $contact->phone,
        'phone' => $contact->phone,
        'email' => $contact->email,
        'user_id' => $contactUser?->id,
        'shared_at' => now()->toISOString(),
    ];

    $message = $group->messages()->create([
        'sender_id' => auth()->id(),
        'body' => '👤 Shared contact',
        'contact_data' => $contactData,
        'delivered_at' => now(),
    ]);

    $message->load(['sender', 'attachments', 'reactions.user']);

    broadcast(new GroupMessageSent($message))->toOthers();

    return response()->json([
        'status' => 'success',
        'message' => $message,
    ]);
}

    /**
     * Reply privately to a message in a group. This will create (or find) a 1:1 conversation
     * between the authenticated user and the original sender of the group message, insert a
     * contextual message indicating the private reply, and redirect the user to that DM.
     *
     * The contextual message simply includes a reference to the group name and a snippet of the
     * original message. If the user attempts to reply privately to their own message, a saved
     * messages conversation is used instead.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Group $group
     * @param \App\Models\GroupMessage $message
     * @return \Illuminate\Http\RedirectResponse
     */
    public function replyPrivate(Request $request, Group $group, GroupMessage $message)
    {
        $currentUser = auth()->user();
        // Ensure the message has its sender loaded
        $message->loadMissing('sender');
        $sender = $message->sender;

        // Authorize that the current user can view the group
        $this->authorize('view-group', $group);

        if (!$sender) {
            return redirect()->back()->withErrors(['error' => 'Original sender not found.']);
        }

        // Determine conversation: saved messages if replying to oneself, otherwise a DM
        if ($sender->id === $currentUser->id) {
            $conversation = \App\Models\Conversation::findOrCreateSavedMessages($currentUser->id);
        } else {
            $conversation = \App\Models\Conversation::findOrCreateDirect($currentUser->id, $sender->id);
        }

        // Don't create a message immediately - instead redirect with session data
        // to show a reply preview that the user can compose their message to
        
        // Store the group message reference in session so we can show it in the reply preview
        session([
            'reply_private_context' => [
                'group_id' => $group->id,
                'group_slug' => $group->slug,
                'group_name' => $group->name,
                'group_message_id' => $message->id,
                'group_message_body' => $message->body,
                'group_message_sender' => $sender->name ?? $sender->phone,
            ]
        ]);

        // Redirect user to the DM conversation view
        return redirect()->route('chat.show', $conversation->slug);
    }

}