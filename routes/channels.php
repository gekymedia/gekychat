<?php

// use Illuminate\Support\Facades\Broadcast;
// use App\Models\Conversation;
// use App\Models\Group;
// use App\Models\User;

// // Broadcast::routes(['middleware' => ['web', 'auth']]);
// // Or create a custom route that handles both formats
// Route::post('/broadcasting/auth', function (Request $request) {
//     // Handle both form-data and JSON requests
//     if ($request->isJson()) {
//         $socketId = $request->json('socket_id');
//         $channelName = $request->json('channel_name');
//     } else {
//         $socketId = $request->input('socket_id');
//         $channelName = $request->input('channel_name');
//     }
    
//     // Use Laravel's built-in broadcast auth
//     return Broadcast::auth($request);
// })->middleware(['web', 'auth']);
// // Primary conversation channel - REMOVED legacy chat.{id} channel
// Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId) {
//     \Log::info('Auth check for conversation channel', [
//         'user_id' => $user->id,
//         'conversation_id' => $conversationId,
//         'channel' => 'conversation.' . $conversationId
//     ]);

//     // Check if user is part of this conversation using the pivot table
//     $hasAccess = Conversation::where('id', $conversationId)
//         ->whereHas('members', function ($query) use ($user) {
//             $query->where('users.id', $user->id);
//         })
//         ->exists();

//     \Log::info('Conversation access result', [
//         'user_id' => $user->id,
//         'conversation_id' => $conversationId,
//         'has_access' => $hasAccess
//     ]);

//     return $hasAccess;
// });

// // Group channels (Presence channels)
// Broadcast::channel('group.{groupId}', function (User $user, int $groupId) {
//     \Log::info('Auth check for group channel', [
//         'user_id' => $user->id,
//         'group_id' => $groupId,
//         'channel' => 'group.' . $groupId
//     ]);

//     $isMember = Group::where('id', $groupId)
//         ->whereHas('members', function ($query) use ($user) {
//             $query->where('users.id', $user->id);
//         })
//         ->exists();

//     \Log::info('Group access result', [
//         'user_id' => $user->id,
//         'group_id' => $groupId,
//         'is_member' => $isMember
//     ]);

//     if (!$isMember) return false;

//     return [
//         'id' => $user->id,
//         'name' => $user->name ?? $user->phone,
//         'avatar' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
//     ];
// });

// // User presence channel for online status
// Broadcast::channel('user.presence.{userId}', function (User $user, int $userId) {
//     return $user->id === $userId ? [
//         'id' => $user->id,
//         'name' => $user->name ?? $user->phone,
//         'avatar' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
//     ] : false;
// });

// // Call channels for WebRTC signalling
// Broadcast::channel('call.{userId}', function (User $user, int $userId) {
//     return $user->id === $userId ? [
//         'id' => $user->id, 
//         'name' => $user->name ?? $user->phone
//     ] : false;
// });

// Broadcast::channel('group.{groupId}.call', function (User $user, int $groupId) {
//     $isMember = Group::where('id', $groupId)
//         ->whereHas('members', function ($query) use ($user) {
//             $query->where('users.id', $user->id);
//         })
//         ->exists();

//     return $isMember ? [
//         'id' => $user->id,
//         'name' => $user->name ?? $user->phone
//     ] : false;
// });



use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\User;

// Broadcast::routes(['middleware' => ['web', 'auth']]);

// Conversation channels (private)
Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId) {
    \Log::info('Auth check for conversation channel', [
        'user_id' => $user->id,
        'conversation_id' => $conversationId,
        'channel' => 'conversation.' . $conversationId
    ]);

    $hasAccess = Conversation::where('id', $conversationId)
        ->whereHas('members', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->exists();

    \Log::info('Conversation access result', [
        'user_id' => $user->id,
        'conversation_id' => $conversationId,
        'has_access' => $hasAccess
    ]);

    return $hasAccess;
});

// Group channels (presence)
Broadcast::channel('group.{groupId}', function (User $user, int $groupId) {
    \Log::info('Auth check for group channel', [
        'user_id' => $user->id,
        'group_id' => $groupId,
        'channel' => 'group.' . $groupId
    ]);

    $isMember = Group::where('id', $groupId)
        ->whereHas('members', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->exists();

    \Log::info('Group access result', [
        'user_id' => $user->id,
        'group_id' => $groupId,
        'is_member' => $isMember
    ]);

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