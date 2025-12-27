<?php
// app/Http/Controllers/BroadcastAuthController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;
use App\Models\Group;

class BroadcastAuthController extends Controller
{
    public function authenticate(Request $request)
    {
        // Ensure user is authenticated
        if (!Auth::check()) {
            Log::warning('Pusher auth: User not authenticated');
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $socketId = $request->socket_id;
        $channelName = $request->channel_name;

        if (!$socketId || !$channelName) {
            Log::error('Pusher auth: Missing socket_id or channel_name', [
                'socket_id' => $socketId,
                'channel_name' => $channelName,
            ]);
            return response()->json(['error' => 'Missing credentials'], 400);
        }

        // PUSHER AUTHENTICATION (ACTIVE)
        // Use Laravel's built-in broadcasting authentication which handles Pusher automatically
        try {
            Log::info('=== PUSHER AUTH REQUEST ===', [
                'channel_name' => $channelName,
                'socket_id' => $socketId,
                'user_id' => Auth::id(),
            ]);

            // Laravel's Broadcast::auth() automatically handles Pusher authentication
            // when BROADCAST_DRIVER=pusher is set in .env
            // It uses the channel authorization in routes/channels.php
            $response = Broadcast::auth($request);
            
            Log::info('=== PUSHER AUTH SUCCESS ===', [
                'channel_name' => $channelName,
                'user_id' => Auth::id()
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Pusher auth exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'channel' => $channelName,
                'user_id' => Auth::id()
            ]);
            return response()->json(['error' => 'Authentication failed: ' . $e->getMessage()], 500);
        }

        // REVERB AUTHENTICATION (COMMENTED OUT - WILL BE RE-ENABLED LATER)
        // if (!$socketId || !$channelName) {
        //     Log::error('Missing socket_id or channel_name for Reverb');
        //     return response()->json(['error' => 'Missing credentials'], 400);
        // }

        // try {
        //     $authData = $this->authenticateReverbChannel($channelName, $socketId);

        //     if (!$authData) {
        //         Log::warning('Reverb channel authentication failed', [
        //             'channel_name' => $channelName,
        //             'user_id' => Auth::id()
        //         ]);
        //         return response()->json(['error' => 'Access denied'], 403);
        //     }

        //     Log::info('=== REVERB AUTH SUCCESS ===', [
        //         'channel_name' => $channelName,
        //         'user_id' => Auth::id()
        //     ]);

        //     return response()->json($authData);

        // } catch (\Exception $e) {
        //     Log::error('Reverb auth exception', [
        //         'error' => $e->getMessage(),
        //         'channel' => $channelName
        //     ]);
        //     return response()->json(['error' => 'Authentication failed'], 500);
        // }
    }

    // REVERB AUTHENTICATION METHODS (COMMENTED OUT - WILL BE RE-ENABLED LATER)
    // private function authenticateReverbChannel(string $channelName, string $socketId)
    // {
    //     $user = Auth::user();
    //     
    //     if (!$user) {
    //         throw new \Exception('No authenticated user');
    //     }

    //     Log::info('Authenticating Reverb channel', [
    //         'channel_name' => $channelName,
    //         'user_id' => $user->id
    //     ]);

    //     // Handle both private- and non-private channel names
    //     $cleanChannelName = str_replace('private-', '', $channelName);

    //     // Handle conversation channels
    //     if (str_starts_with($cleanChannelName, 'conversation.')) {
    //         $conversationId = (int) str_replace('conversation.', '', $cleanChannelName);
    //         return $this->authenticateConversation($user->id, $conversationId, $socketId);
    //     }

    //     // Handle group channels
    //     if (str_starts_with($cleanChannelName, 'group.')) {
    //         $groupId = (int) str_replace('group.', '', $cleanChannelName);
    //         return $this->authenticateGroup($user->id, $groupId, $socketId);
    //     }

    //     // Handle user presence channels
    //     if (str_starts_with($cleanChannelName, 'user.presence.')) {
    //         $userId = (int) str_replace('user.presence.', '', $cleanChannelName);
    //         return $this->authenticateUserChannel($user->id, $userId, $socketId);
    //     }

    //     Log::warning('Unknown Reverb channel type', ['channel_name' => $channelName]);
    //     return false;
    // }

    // private function authenticateConversation(int $userId, int $conversationId, string $socketId)
    // {
    //     $isMember = Conversation::where('id', $conversationId)
    //         ->whereHas('members', function ($query) use ($userId) {
    //             $query->where('users.id', $userId);
    //         })
    //         ->exists();

    //     if (!$isMember) {
    //         Log::warning('User not member of conversation', [
    //             'user_id' => $userId,
    //             'conversation_id' => $conversationId
    //         ]);
    //         return false;
    //     }

    //     Log::info('Reverb conversation authentication successful', [
    //         'user_id' => $userId,
    //         'conversation_id' => $conversationId
    //     ]);

    //     return [
    //         'auth' => $socketId . ':' . $conversationId
    //     ];
    // }

    // private function authenticateGroup(int $userId, int $groupId, string $socketId)
    // {
    //     $isMember = Group::where('id', $groupId)
    //         ->whereHas('members', function ($query) use ($userId) {
    //             $query->where('users.id', $userId);
    //         })
    //         ->exists();

    //     if (!$isMember) {
    //         Log::warning('User not member of group', [
    //             'user_id' => $userId,
    //             'group_id' => $groupId
    //         ]);
    //         return false;
    //     }

    //     Log::info('Reverb group authentication successful', [
    //         'user_id' => $userId,
    //         'group_id' => $groupId
    //     ]);

    //     return [
    //         'auth' => $socketId . ':' . $groupId,
    //         'channel_data' => json_encode([
    //             'user_id' => $userId,
    //             'user_info' => [
    //                 'id' => $userId,
    //                 'name' => Auth::user()->name ?? Auth::user()->phone,
    //             ]
    //         ])
    //     ];
    // }

    // private function authenticateUserChannel(int $userId, int $targetUserId, string $socketId)
    // {
    //     // Users can only listen to their own presence channel
    //     if ($userId !== $targetUserId) {
    //         Log::warning('User attempted to access another user channel', [
    //             'user_id' => $userId,
    //             'target_user_id' => $targetUserId
    //         ]);
    //         return false;
    //     }

    //     return [
    //         'auth' => $socketId . ':' . $targetUserId,
    //         'channel_data' => json_encode([
    //             'user_id' => $userId,
    //             'user_info' => [
    //                 'id' => $userId,
    //                 'name' => Auth::user()->name ?? Auth::user()->phone,
    //             ]
    //         ])
    //     ];
    // }
}