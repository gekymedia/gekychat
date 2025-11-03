<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\User;

// Conversation channels (private)
Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId) {
    $hasAccess = Conversation::where('id', $conversationId)
        ->whereHas('members', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->exists();

    return $hasAccess;
});

// PRESENCE CHANNELS - ADD THESE
Broadcast::channel('presence-conversation.{conversationId}', function (User $user, int $conversationId) {
    $hasAccess = Conversation::where('id', $conversationId)
        ->whereHas('members', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->exists();

    if (!$hasAccess) return false;

    return [
        'id' => $user->id,
        'name' => $user->name ?? $user->phone,
        'avatar' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
    ];
});

// Group channels (presence)
Broadcast::channel('group.{groupId}', function (User $user, int $groupId) {
    $isMember = Group::where('id', $groupId)
        ->whereHas('members', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->exists();

    if (!$isMember) return false;

    return [
        'id' => $user->id,
        'name' => $user->name ?? $user->phone,
        'avatar' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
    ];
});

// PRESENCE GROUP CHANNEL
Broadcast::channel('presence-group.{groupId}', function (User $user, int $groupId) {
    $isMember = Group::where('id', $groupId)
        ->whereHas('members', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->exists();

    if (!$isMember) return false;

    return [
        'id' => $user->id,
        'name' => $user->name ?? $user->phone,
        'avatar' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
    ];
});

// User presence channel
Broadcast::channel('user.presence.{userId}', function (User $user, int $userId) {
    return $user->id === $userId ? [
        'id' => $user->id,
        'name' => $user->name ?? $user->phone,
        'avatar' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
    ] : false;
});