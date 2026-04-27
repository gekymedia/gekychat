<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\User;
use App\Models\WorldFeedPost;

// Note: Removed the problematic "private-conversation.*" wildcard channel
// Clients should subscribe to specific conversation channels instead:
// Example: private-conversation.1, private-conversation.2, etc.

// Conversation channels (private)
Broadcast::channel('conversation.{conversationId}', function (User $user, $conversationId) {
    // Handle wildcard or non-numeric values
    if (!is_numeric($conversationId) || $conversationId === '*' || $conversationId === 'null') {
        return false;
    }
    
    $conversationId = (int) $conversationId;
    $conversation = Conversation::find($conversationId);
    
    if (!$conversation) {
        return false;
    }
    
    // Private channel (Echo.private): must return bool. Presence uses
    // presence-conversation.{id} below.
    return $conversation->isParticipant($user->id);
});

// PRESENCE CHANNELS - ADD THESE
Broadcast::channel('presence-conversation.{conversationId}', function (User $user, $conversationId) {
    // Handle wildcard or non-numeric values
    if (!is_numeric($conversationId) || $conversationId === '*' || $conversationId === 'null') {
        return false;
    }
    
    $conversationId = (int) $conversationId;
    \Log::info('Channel auth: presence-conversation', [
        'user_id' => $user->id,
        'conversation_id' => $conversationId,
    ]);
    
    $conversation = Conversation::find($conversationId);
    
    if (!$conversation) {
        \Log::warning('Channel auth denied: presence-conversation - conversation not found', [
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
        ]);
        return false;
    }
    
    // Check if user is a participant (works for saved messages too)
    $hasAccess = $conversation->isParticipant($user->id);

    if (!$hasAccess) {
        \Log::warning('Channel auth denied: presence-conversation', [
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
        ]);
        return false;
    }

    \Log::info('Channel auth allowed: presence-conversation', [
        'user_id' => $user->id,
        'conversation_id' => $conversationId,
    ]);

    return [
        'id' => $user->id,
        'name' => $user->name ?? $user->phone,
        'avatar' => $user->avatar_path ? url('storage/'.$user->avatar_path) : null,
    ];
});

// Group channels (presence)
Broadcast::channel('group.{groupId}', function (User $user, $groupId) {
    // Handle wildcard or non-numeric values
    if (!is_numeric($groupId) || $groupId === '*' || $groupId === 'null') {
        return false;
    }
    
    $groupId = (int) $groupId;
    $isMember = Group::where('id', $groupId)
        ->whereHas('members', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->exists();

    // Private channel (Echo.private): must return bool.
    return $isMember;
});

// Group call channels (for WebRTC signaling)
Broadcast::channel('group.{groupId}.call', function (User $user, $groupId) {
    // Handle wildcard or non-numeric values
    if (!is_numeric($groupId) || $groupId === '*' || $groupId === 'null') {
        return false;
    }
    
    $groupId = (int) $groupId;
    \Log::info('Channel auth: group.{groupId}.call', [
        'user_id' => $user->id,
        'group_id' => $groupId,
    ]);
    
    $isMember = Group::where('id', $groupId)
        ->whereHas('members', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->exists();

    if (!$isMember) {
        \Log::warning('Channel auth denied: group.{groupId}.call - not a member', [
            'user_id' => $user->id,
            'group_id' => $groupId,
        ]);
        return false;
    }

    \Log::info('Channel auth allowed: group.{groupId}.call', [
        'user_id' => $user->id,
        'group_id' => $groupId,
    ]);

    return true; // Private channels return true/false, not presence data
});

// PRESENCE GROUP CHANNEL
Broadcast::channel('presence-group.{groupId}', function (User $user, $groupId) {
    // Handle wildcard or non-numeric values
    if (!is_numeric($groupId) || $groupId === '*' || $groupId === 'null') {
        return false;
    }
    
    $groupId = (int) $groupId;
    \Log::info('Channel auth: presence-group', [
        'user_id' => $user->id,
        'group_id' => $groupId,
    ]);
    
    $isMember = Group::where('id', $groupId)
        ->whereHas('members', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->exists();

    if (!$isMember) {
        \Log::warning('Channel auth denied: presence-group', [
            'user_id' => $user->id,
            'group_id' => $groupId,
        ]);
        return false;
    }

    \Log::info('Channel auth allowed: presence-group', [
        'user_id' => $user->id,
        'group_id' => $groupId,
    ]);

    return [
        'id' => $user->id,
        'name' => $user->name ?? $user->phone,
        'avatar' => $user->avatar_path ? url('storage/'.$user->avatar_path) : null,
    ];
});

// User presence channel (Echo.join('user.1') becomes 'presence-user.1')
Broadcast::channel('presence-user.{userId}', function (User $user, $userId) {
    // Handle wildcard or non-numeric values
    if (!is_numeric($userId) || $userId === '*' || $userId === 'null') {
        return false;
    }
    
    $userId = (int) $userId;
    \Log::info('Channel auth: presence-user', [
        'user_id' => $user->id,
        'channel_user_id' => $userId,
    ]);
    
    // Users can only subscribe to their own presence channel
    if ($user->id !== $userId) {
        \Log::warning('Channel auth denied: presence-user - user mismatch', [
            'user_id' => $user->id,
            'channel_user_id' => $userId,
        ]);
        return false;
    }
    
    \Log::info('Channel auth allowed: presence-user', [
        'user_id' => $user->id,
        'channel_user_id' => $userId,
    ]);
    
    return [
        'id' => $user->id,
        'name' => $user->name ?? $user->phone,
        'avatar' => $user->avatar_path ? url('storage/'.$user->avatar_path) : null,
    ];
});

// Private user channel (for notifications, etc.)
Broadcast::channel('user.{userId}', function (User $user, $userId) {
    // Handle wildcard or non-numeric values
    if (!is_numeric($userId) || $userId === '*' || $userId === 'null') {
        return false;
    }
    
    $userId = (int) $userId;
    // Private channel (Echo.private('user.X')): return bool only.
    // Presence on the same logical channel uses presence-user.{id} above.
    return (int) $user->id === $userId;
});

// Call channels (for direct calls - fallback)
Broadcast::channel('call.{userId}', function (User $user, $userId) {
    // Handle wildcard or non-numeric values
    if (!is_numeric($userId) || $userId === '*' || $userId === 'null') {
        return false;
    }
    
    $userId = (int) $userId;
    \Log::info('Channel auth: call.{userId}', [
        'user_id' => $user->id,
        'call_user_id' => $userId,
    ]);
    
    // Users can subscribe to calls where they are the callee
    // Or if they are the caller (for signaling)
    $hasAccess = $user->id === $userId;
    
    if (!$hasAccess) {
        \Log::warning('Channel auth denied: call.{userId}', [
            'user_id' => $user->id,
            'call_user_id' => $userId,
        ]);
    } else {
        \Log::info('Channel auth allowed: call.{userId}', [
            'user_id' => $user->id,
            'call_user_id' => $userId,
        ]);
    }
    
    return $hasAccess;
});

// World feed: post owner only — floating likes/comments while viewing own post
Broadcast::channel('world-feed-post.{postId}', function (User $user, $postId) {
    if (! is_numeric($postId) || $postId === '*' || $postId === 'null') {
        return false;
    }

    $postId = (int) $postId;
    $post = WorldFeedPost::find($postId);

    if (! $post) {
        return false;
    }

    return (int) $user->id === (int) $post->creator_id;
});