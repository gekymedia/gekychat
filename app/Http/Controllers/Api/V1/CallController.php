<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\CallSignal;
use App\Events\CallInvite;
use App\Events\GroupMessageSent;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\CallParticipant;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\Message;
use App\Models\User;
use App\Services\FeatureFlagService;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CallController extends Controller
{
    /**
     * POST /api/v1/calls/start
     * Body: { "callee_id": 2, "group_id": null, "type": "video" }
     * Creates a new call session and notifies the callee(s).
     */
    public function start(Request $request)
    {
        // Support both Sanctum (API) and Session (Web) authentication
        $user = $request->user() ?? auth()->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }
        $data = $request->validate([
            'callee_id' => ['nullable', 'numeric', 'exists:users,id'],
            'group_id'  => ['nullable', 'numeric', 'exists:groups,id'],
            'conversation_id' => ['nullable', 'numeric', 'exists:conversations,id'],
            'type'      => ['required', 'in:voice,video'],
            'is_meeting' => ['nullable', 'boolean'], // PHASE 2: Meeting-style call
        ]);
        
        // Ensure we have at least one of callee_id, group_id, or conversation_id
        $calleeId = $data['callee_id'] ?? null;
        $groupId = $data['group_id'] ?? null;
        $conversationId = $data['conversation_id'] ?? null;
        
        // Count how many identifiers we have
        $identifierCount = count(array_filter([$calleeId, $groupId, $conversationId]));
        
        if ($identifierCount === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'callee_id, group_id, or conversation_id is required.',
            ], 422);
        }
        if ($identifierCount > 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Provide only one of callee_id, group_id, or conversation_id.',
            ], 422);
        }
        // Determine conversation and group
        $conversation = null;
        $group = null;
        $callLink = null;
        
        if ($conversationId) {
            // Direct conversation call
            $conversation = Conversation::findOrFail($conversationId);
            
            // Ensure user is a participant
            if (!$conversation->isParticipant($user->id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not a participant in this conversation.',
                ], 403);
            }
            
            // Get or generate call link
            $callLink = $conversation->call_link;
            $calleeId = $conversation->otherParticipant($user->id)?->id;
        } elseif ($groupId) {
            // Group call
            $group = Group::findOrFail($groupId);
            
            // Reject channels - they don't support calls
            if ($group->type === 'channel') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Channels do not support calls.',
                ], 422);
            }
            
            // Ensure user is a member
            if (!$group->isMember($user)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not a member of this group.',
                ], 403);
            }
            
            // Get or generate call link
            $callLink = $group->call_link;
        } else {
            // Direct call via callee_id
            $conversation = Conversation::findOrCreateDirect($user->id, $calleeId);
            $callLink = $conversation->call_link;
        }
        
        // PHASE 2: Check feature flags
        $isMeeting = $data['is_meeting'] ?? false;
        if ($isMeeting && !FeatureFlagService::isEnabled('meeting_mode', $user)) {
            return response()->json(['message' => 'Meeting mode is not available'], 403);
        }
        if ($groupId && !FeatureFlagService::isEnabled('group_calls', $user)) {
            return response()->json(['message' => 'Group calls are not available'], 403);
        }

        // PHASE 2: Generate invite token for meetings or group calls
        $inviteToken = ($isMeeting || $groupId) ? Str::random(32) : null;

        // Create call session
        $call = CallSession::create([
            'caller_id' => $user->id,
            'callee_id' => $calleeId,
            'group_id'  => $groupId,
            'type'      => $data['type'],
            'status'    => 'pending',
            'is_meeting' => $isMeeting,
            'invite_token' => $inviteToken,
            'host_id' => $isMeeting ? $user->id : null, // PHASE 2: Set host for meetings
            // started_at will be set when the call is answered
        ]);

        // PHASE 2: Create participant records for group calls/meetings
        if ($groupId || $isMeeting) {
            // Add caller as participant
            CallParticipant::create([
                'call_session_id' => $call->id,
                'user_id' => $user->id,
                'status' => 'joined',
                'joined_at' => now(),
                'is_host' => $isMeeting ? true : false,
            ]);

            // If group call, invite all group members
            if ($groupId) {
                $group = Group::findOrFail($groupId);
                $members = $group->members()->where('user_id', '!=', $user->id)->get();
                
                foreach ($members as $member) {
                    CallParticipant::create([
                        'call_session_id' => $call->id,
                        'user_id' => $member->id,
                        'status' => 'invited',
                    ]);
                }
            }
        }
        
        // Create a message in the conversation showing call link
        try {
            $callTypeText = $data['type'] === 'video' ? 'video call' : 'voice call';
            $callerName = $user->name ?? $user->phone ?? 'Someone';
            
            if ($group) {
                // For group calls, create a GroupMessage with call link
                $callData = [
                    'type' => $data['type'],
                    'caller_id' => $user->id,
                    'session_id' => $call->id,
                    'status' => 'calling',
                    'call_link' => $callLink,
                ];
                
                $body = "{$callerName} started a {$callTypeText}";
                
                $groupMessage = GroupMessage::create([
                    'group_id' => $groupId,
                    'sender_id' => $user->id,
                    'body' => $body,
                    'call_data' => $callData,
                    'is_encrypted' => false,
                ]);
                
                $groupMessage->load(['sender', 'attachments', 'reactions.user']);
                // Broadcast to all including caller so they see the call message in chat
                broadcast(new GroupMessageSent($groupMessage));
                
            } else {
                // For direct calls, create message with call link
                $callData = [
                    'type' => $data['type'],
                    'caller_id' => $user->id,
                    'session_id' => $call->id,
                    'status' => 'calling',
                    'call_link' => $callLink,
                ];
                
                $body = "{$callerName} started a {$callTypeText}";
                
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $user->id,
                    'body' => $body,
                    'call_data' => $callData,
                    'is_encrypted' => false,
                ]);
                
                $message->load(['sender', 'attachments', 'reactions.user']);
                broadcast(new MessageSent($message))->toOthers();
            }
        } catch (\Exception $e) {
            // Log error but don't fail the call start
            \Log::error('Failed to create calling message: ' . $e->getMessage());
        }
        
        // Prepare caller info
        $callerInfo = [
            'id'     => $user->id,
            'name'   => $user->name ?? $user->phone,
            'avatar' => $user->avatar_url ?? null,
        ];
        
        // Broadcast invite to callee's private channel (all their devices)
        if ($calleeId) {
            broadcast(new CallInvite($call, $callerInfo))->toOthers();
        }
        
        // Also broadcast signal on conversation channel for WebRTC signaling
        $payload = json_encode([
            'session_id' => $call->id,
            'type'       => $call->type,
            'caller'     => $callerInfo,
            'action'     => 'invite',
        ]);
        broadcast(new CallSignal($call, $payload))->toOthers();
        
        // Send FCM push notification for call (if callee is available)
        if ($calleeId) {
            try {
                $callee = User::find($calleeId);
                if ($callee) {
                    // Trigger FCM notification via queue
                    \App\Jobs\SendCallNotification::dispatch($callee, $call, $user);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to queue call notification: ' . $e->getMessage());
            }
        }
        
        return response()->json([
            'status'     => 'success',
            'session_id' => $call->id,
            'call_link'  => $callLink,
        ]);
    }

    /**
     * POST /api/v1/calls/{session}/signal
     * Body: { "payload": "..." }
     * Forwards signalling data (offer/answer/ICE) to the other party.
     */
    public function signal(Request $request, CallSession $session)
    {
        // Support both Sanctum (API) and Session (Web) authentication
        $user = $request->user() ?? auth()->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }
        // Authorize: either caller or callee or group member
        if ($session->group_id) {
            $group = $session->group;
            Gate::authorize('manage-group', $group);
        } else {
            if ($user->id !== $session->caller_id && $user->id !== $session->callee_id) {
                return response()->json(['status' => 'error', 'message' => 'Not authorized'], 403);
            }
        }
        $data = $request->validate([
            'payload' => ['required', 'string'],
        ]);
        
        // If payload contains an answer, mark call as started
        $payloadData = json_decode($data['payload'], true);
        if (is_array($payloadData) && isset($payloadData['type']) && $payloadData['type'] === 'answer' && !$session->started_at) {
            $session->update([
                'status' => 'ongoing',
                'started_at' => now(),
            ]);
        }
        
        broadcast(new CallSignal($session, $data['payload']))->toOthers();
        return response()->json(['status' => 'success']);
    }

    /**
     * POST /api/v1/calls/{session}/end
     * Marks the call as ended and notifies participants.
     */
    public function end(Request $request, CallSession $session)
    {
        // Support both Sanctum (API) and Session (Web) authentication
        $user = $request->user() ?? auth()->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }
        // Authorize
        if ($session->group_id) {
            Gate::authorize('manage-group', $session->group);
        } else {
            if ($user->id !== $session->caller_id && $user->id !== $session->callee_id) {
                return response()->json(['status' => 'error', 'message' => 'Not authorized'], 403);
            }
        }
        // Calculate call duration
        $duration = null;
        $startTime = $session->started_at ?? $session->created_at;
        if ($startTime) {
            $duration = $startTime->diffInSeconds(now());
        }

        $session->update([
            'status'    => 'ended',
            'ended_at'  => now(),
        ]);

        // Create a message in the conversation to show the call
        try {
            // Check if call was missed (no started_at means it was never answered, or very short duration)
            $isMissed = !$session->started_at || ($duration !== null && $duration < 2);
            
            if ($session->group_id) {
                // For group calls, create a GroupMessage showing call ended
                $group = Group::find($session->group_id);
                if ($group) {
                    $callerName = $session->caller->name ?? $session->caller->phone ?? 'Someone';
                    $callTypeText = $session->type === 'video' ? 'video call' : 'voice call';
                    
                    $callData = [
                        'type' => $session->type,
                        'caller_id' => $session->caller_id,
                        'session_id' => $session->id,
                        'status' => 'ended',
                        'duration' => $duration,
                        'is_missed' => $isMissed,
                    ];
                    
                    $callIcon = $session->type === 'video' ? '📹' : '📞';
                    if ($isMissed) {
                        $body = "{$callIcon} Missed {$callTypeText}";
                    } elseif ($duration && $duration > 0) {
                        $durationText = $duration < 60 
                            ? "{$duration}s" 
                            : gmdate('i:s', $duration);
                        $body = "{$callIcon} {$callTypeText} ({$durationText})";
                    } else {
                        $body = "{$callIcon} {$callTypeText}";
                    }
                    
                    $groupMessage = GroupMessage::create([
                        'group_id' => $session->group_id,
                        'sender_id' => $session->caller_id,
                        'body' => $body,
                        'call_data' => $callData,
                        'is_encrypted' => false,
                    ]);
                    
                    $groupMessage->load(['sender', 'attachments', 'reactions.user']);
                    broadcast(new GroupMessageSent($groupMessage))->toOthers();
                }
            } else {
                // For direct calls, find or create conversation between caller and callee
                if (!$session->caller_id || !$session->callee_id) {
                    \Log::warning('Call session missing caller_id or callee_id', [
                        'session_id' => $session->id,
                        'caller_id' => $session->caller_id,
                        'callee_id' => $session->callee_id,
                    ]);
                    return response()->json(['error' => 'Invalid call session'], 400);
                }
                
                // Find or create conversation between caller and callee
                $conversation = Conversation::findOrCreateDirect($session->caller_id, $session->callee_id);
                
                $callData = [
                    'type' => $session->type, // 'voice' or 'video'
                    'caller_id' => $session->caller_id,
                    'callee_id' => $session->callee_id,
                    'duration' => $duration,
                    'ended_at' => now()->toISOString(),
                    'missed' => $isMissed,
                ];

                // Determine message body based on call type and outcome
                $callIcon = $session->type === 'video' ? '📹' : '📞';
                $callTypeText = $session->type === 'video' ? 'Video call' : 'Voice call';
                
                if ($isMissed) {
                    $body = "{$callIcon} Missed {$callTypeText}";
                } elseif ($duration && $duration > 0) {
                    $durationText = $duration < 60 
                        ? "{$duration}s" 
                        : gmdate('i:s', $duration);
                    $body = "{$callIcon} {$callTypeText} ({$durationText})";
                } else {
                    $body = "{$callIcon} {$callTypeText}";
                }

                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $session->caller_id,
                    'body' => $body,
                    'call_data' => $callData,
                    'is_encrypted' => false,
                ]);

                $message->load(['sender', 'attachments', 'reactions.user']);
                broadcast(new MessageSent($message))->toOthers();
            }
        } catch (\Exception $e) {
            // Log error but don't fail the call end
            \Log::error('Failed to create call message: ' . $e->getMessage());
        }

        $payload = json_encode([
            'session_id' => $session->id,
            'action'     => 'ended',
        ]);
        broadcast(new CallSignal($session, $payload))->toOthers();
        return response()->json(['status' => 'success']);
    }

    /**
     * GET /calls/join/{callId}
     * Join a call via unique call link. Verifies user is a participant.
     */
    public function join(Request $request, string $callId)
    {
        // Support both Sanctum (API) and Session (Web) authentication
        $user = $request->user() ?? auth()->user();
        if (!$user) {
            // For web, redirect to login
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated'
                ], 401);
            }
            return redirect()->route('login')->with('error', 'Please login to join the call.');
        }

        // Find conversation or group by call_id
        $conversation = Conversation::where('call_id', $callId)->first();
        $group = null;
        
        if (!$conversation) {
            $group = Group::where('call_id', $callId)->first();
        }

        if (!$conversation && !$group) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Call not found or invalid call link.'
                ], 404);
            }
            return redirect()->route('chat.index')->with('error', 'Call not found or invalid call link.');
        }

        // Verify user is a participant
        if ($conversation) {
            if (!$conversation->isParticipant($user->id)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'You are not authorized to join this call.'
                    ], 403);
                }
                return redirect()->route('chat.index')->with('error', 'You are not authorized to join this call.');
            }
        } elseif ($group) {
            // Reject channels
            if ($group->type === 'channel') {
                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Channels do not support calls.'
                    ], 422);
                }
                return redirect()->route('groups.show', $group->slug)->with('error', 'Channels do not support calls.');
            }

            if (!$group->isMember($user)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'You are not a member of this group.'
                    ], 403);
                }
                return redirect()->route('groups.show', $group->slug)->with('error', 'You are not a member of this group.');
            }
        }

        // Find the active call session for this conversation/group
        // Look for the most recent active call that matches this call_id
        $callSession = null;
        if ($conversation) {
            $calleeId = $conversation->otherParticipant($user->id)?->id;
            
            // First, try to find a call session from a recent call message in this conversation
            $recentCallMessage = Message::where('conversation_id', $conversation->id)
                ->whereNotNull('call_data')
                ->where(function ($query) {
                    $query->where('call_data->status', 'calling')
                          ->orWhere('call_data->status', 'ongoing');
                })
                ->latest()
                ->first();
            
            if ($recentCallMessage && isset($recentCallMessage->call_data['session_id'])) {
                // Find the call session from the message
                $callSession = CallSession::find($recentCallMessage->call_data['session_id']);
            }
            
            // If not found, look for any active call between these users
            if (!$callSession || !in_array($callSession->status, ['pending', 'ongoing'])) {
                $callSession = CallSession::where(function($query) use ($user, $calleeId) {
                    $query->where(function($q) use ($user, $calleeId) {
                        $q->where('caller_id', $user->id)
                          ->where('callee_id', $calleeId);
                    })->orWhere(function($q) use ($user, $calleeId) {
                        $q->where('callee_id', $user->id)
                          ->where('caller_id', $calleeId);
                    });
                })
                ->whereNull('group_id')
                ->whereIn('status', ['pending', 'ongoing'])
                ->latest()
                ->first();
            }
        } elseif ($group) {
            // First, try to find a call session from a recent call message in this group
            $recentCallMessage = GroupMessage::where('group_id', $group->id)
                ->whereNotNull('call_data')
                ->where(function ($query) {
                    $query->where('call_data->status', 'calling')
                          ->orWhere('call_data->status', 'ongoing');
                })
                ->latest()
                ->first();
            
            if ($recentCallMessage && isset($recentCallMessage->call_data['session_id'])) {
                // Find the call session from the message
                $callSession = CallSession::find($recentCallMessage->call_data['session_id']);
            }
            
            // If not found, look for any active group call
            if (!$callSession || !in_array($callSession->status, ['pending', 'ongoing'])) {
                $callSession = CallSession::where('group_id', $group->id)
                    ->whereIn('status', ['pending', 'ongoing'])
                    ->latest()
                    ->first();
            }
        }
        
        // If still no active call found, don't create a new one - return error
        if (!$callSession || !in_array($callSession->status, ['pending', 'ongoing'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No active call found to join.'
                ], 404);
            }
            return redirect()->back()->with('error', 'No active call found to join.');
        }

        // Check if this is an AJAX request (for modal join)
        if ($request->ajax() || $request->wantsJson() || $request->expectsJson()) {
            // Return JSON for AJAX requests (modal join)
            return response()->json([
                'status' => 'success',
                'session_id' => $callSession->id,
                'type' => $callSession->type,
                'call_link' => $conversation ? $conversation->call_link : ($group ? $group->call_link : null),
                'conversation_id' => $conversation?->id,
                'group_id' => $group?->id,
            ]);
        }

        // For regular web requests, redirect to the chat page with call auto-start
        if ($conversation) {
            return redirect()->route('chat.show', $conversation->slug)
                ->with('auto_start_call', $callSession->id)
                ->with('call_type', $callSession->type);
        } elseif ($group) {
            return redirect()->route('groups.show', $group->slug)
                ->with('auto_start_call', $callSession->id)
                ->with('call_type', $callSession->type);
        }

        // For API requests, return call session info
        return response()->json([
            'status' => 'success',
            'session_id' => $callSession->id,
            'type' => $callSession->type,
            'call_link' => $conversation ? $conversation->call_link : $group->call_link,
            'conversation_id' => $conversation?->id,
            'group_id' => $group?->id,
        ]);
    }

    /**
     * PHASE 1: Get TURN/ICE server configuration for WebRTC
     * GET /api/v1/calls/config
     * 
     * Returns TURN server configuration if feature flag is enabled.
     */
    public function config(Request $request)
    {
        $user = $request->user() ?? auth()->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        // PHASE 1: Check if improved call stack feature is enabled
        $improvedCallsEnabled = \App\Services\FeatureFlagService::isEnabled('improved_call_stack', $user);
        
        $config = [
            'stun' => [
                ['urls' => 'stun:stun.l.google.com:19302'],
                ['urls' => 'stun:stun1.l.google.com:19302'],
            ],
            'turn' => [],
        ];

        // Include TURN servers if configured (enable for all users if TURN is configured)
        // Feature flag is optional - if TURN is configured, use it
        $turnConfig = config('services.webrtc.turn');
        
        if ($turnConfig['enabled'] && !empty($turnConfig['urls']) && 
            $turnConfig['username'] && $turnConfig['credential']) {
            
            // Enable TURN for all users if configured (feature flag is optional)
            foreach ($turnConfig['urls'] as $url) {
                if (!empty(trim($url))) {
                    $config['turn'][] = [
                        'urls' => trim($url),
                        'username' => $turnConfig['username'],
                        'credential' => $turnConfig['credential'],
                    ];
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'config' => $config,
            'improved_call_stack_enabled' => $improvedCallsEnabled,
        ]);
    }
}