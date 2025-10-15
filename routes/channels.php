<?php


use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Use Sanctum for API/mobile broadcast auth (bearer token), works for web too if session present
Broadcast::routes(['middleware' => ['auth:sanctum']]);


// DMs: private channel `chat.{conversationId}`
Broadcast::channel('chat.{conversationId}', function (User $user, int $conversationId) {
return Conversation::where('id', $conversationId)
->where(function ($q) use ($user) {
$q->where('user_one_id', $user->id)
->orWhere('user_two_id', $user->id);
})
->exists();
});


// Groups: presence channel `group.{groupId}`
Broadcast::channel('group.{groupId}', function (User $user, int $groupId) {
$isMember = Group::where('id', $groupId)
->whereHas('members', fn($q) => $q->where('users.id', $user->id))
->exists();


if (! $isMember) return false;


return [
'id' => $user->id,
'name' => $user->name ?? $user->phone,
'avatar' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
];
});


Broadcast::channel('private-conversation.{id}', function ($user, $id) {
    $isMember = DB::table('conversation_user')
        ->where('conversation_id', $id)
        ->where('user_id', $user->id)
        ->exists();

    return $isMember ? ['id' => $user->id, 'name' => $user->name] : false;
});

// Call channels for WebRTC signalling
// 1:1 call channel: `call.{userId}` – only the callee and caller can listen
Broadcast::channel('call.{userId}', function (User $user, int $userId) {
    // Authorize if the authenticated user is the intended recipient
    return $user->id === $userId ? ['id' => $user->id, 'name' => $user->name] : false;
});

// Group call signalling channel: `group.{groupId}.call` – group members only
Broadcast::channel('group.{groupId}.call', function (User $user, int $groupId) {
    return Group::where('id', $groupId)
        ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
        ->exists() ? ['id' => $user->id, 'name' => $user->name] : false;
});
