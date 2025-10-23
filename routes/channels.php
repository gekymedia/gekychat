<?php

// use Illuminate\Support\Facades\Broadcast;
// use App\Models\Conversation;
// use App\Models\Group;
// use App\Models\User;
// use Illuminate\Support\Facades\DB;

// // ✅ Use web middleware for traditional Laravel apps
// Broadcast::routes(['middleware' => ['web', 'auth']]);

// // ✅ DMs - Private channels for direct messages
// Broadcast::channel('chat.{conversationId}', function (User $user, int $conversationId) {
//     \Log::info('Checking DM channel access', [
//         'user_id' => $user->id,
//         'conversation_id' => $conversationId
//     ]);
    
//     return Conversation::where('id', $conversationId)
//         ->where(function ($q) use ($user) {
//             $q->where('user_one_id', $user->id)
//               ->orWhere('user_two_id', $user->id);
//         })
//         ->exists();
// });

// // ✅ Groups - Presence channels for groups
// Broadcast::channel('group.{groupId}', function (User $user, int $groupId) {
//     \Log::info('Checking group channel access', [
//         'user_id' => $user->id,
//         'group_id' => $groupId
//     ]);
    
//     $isMember = Group::where('id', $groupId)
//         ->whereHas('members', fn($q) => $q->where('users.id', $user->id))
//         ->exists();

//     if (!$isMember) return false;

//     return [
//         'id' => $user->id,
//         'name' => $user->name ?? $user->phone,
//         'avatar' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
//     ];
// });

// // ✅ User presence channel for online status
// Broadcast::channel('user.presence.{userId}', function (User $user, int $userId) {
//     return $user->id === $userId ? [
//         'id' => $user->id,
//         'name' => $user->name ?? $user->phone,
//         'avatar' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
//     ] : false;
// });

// // Call channels for WebRTC signalling
// Broadcast::channel('call.{userId}', function (User $user, int $userId) {
//     return $user->id === $userId ? ['id' => $user->id, 'name' => $user->name] : false;
// });

// Broadcast::channel('group.{groupId}.call', function (User $user, int $groupId) {
//     return Group::where('id', $groupId)
//         ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
//         ->exists() ? ['id' => $user->id, 'name' => $user->name] : false;
// });


use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\User;

Broadcast::routes(['middleware' => ['web', 'auth']]);

// Debug authentication
Broadcast::channel('chat.{conversationId}', function (User $user, int $conversationId) {
    \Log::info('Auth check for conversation', [
        'user_id' => $user->id,
        'conversation_id' => $conversationId,
        'authenticated' => auth()->check(),
        'user_agent' => request()->userAgent()
    ]);

    // Check if user is part of this conversation
    $hasAccess = Conversation::where('id', $conversationId)
        ->where(function ($query) use ($user) {
            $query->where('user_one_id', $user->id)
                  ->orWhere('user_two_id', $user->id);
        })
        ->exists();

    \Log::info('Conversation access result', [
        'user_id' => $user->id,
        'conversation_id' => $conversationId,
        'has_access' => $hasAccess
    ]);

    return $hasAccess;
});

// Group channels
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