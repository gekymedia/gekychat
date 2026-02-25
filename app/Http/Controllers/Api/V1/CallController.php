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
        // Group calls: only block when the feature flag exists and is explicitly disabled (default: allow)
        if ($groupId) {
            $groupCallsFlag = \App\Models\FeatureFlag::where('key', 'group_calls')->first();
            if ($groupCallsFlag && !$groupCallsFlag->enabled) {
                return response()->json(['message' => 'Group calls are not available'], 403);
            }
        }

        // startOrJoin: one active call per group/conversation – if one exists and link not expired, return it
        $existingCall = null;
        if ($groupId) {
            $existingCall = CallSession::where('group_id', $groupId)
                ->whereIn('status', ['pending', 'calling', 'ongoing'])
                ->notExpired()
                ->orderByDesc('created_at')
                ->first();
        } elseif ($conversation && $calleeId) {
            $existingCall = CallSession::whereNull('group_id')
                ->whereIn('status', ['pending', 'calling', 'ongoing'])
                ->notExpired()
                ->where(function ($q) use ($user, $calleeId) {
                    $q->where(function ($q2) use ($user, $calleeId) {
                        $q2->where('caller_id', $user->id)->where('callee_id', $calleeId);
                    })->orWhere(function ($q2) use ($user, $calleeId) {
                        $q2->where('caller_id', $calleeId)->where('callee_id', $user->id);
                    });
                })
                ->orderByDesc('created_at')
                ->first();
        }

        if ($existingCall) {
            return response()->json([
                'status'     => 'success',
                'session_id' => $existingCall->id,
                'call_link'  => $callLink,
                'caller_id'  => $existingCall->caller_id,
                'callee_id'  => $existingCall->callee_id,
            ]);
        }

        // PHASE 2: Generate invite token for meetings or group calls
        $inviteToken = ($isMeeting || $groupId) ? Str::random(32) : null;

        // Create call session (caller counts as first "join" for 24h link expiry)
        $call = CallSession::create([
            'caller_id' => $user->id,
            'callee_id' => $calleeId,
            'group_id'  => $groupId,
            'type'      => $data['type'],
            'status'    => 'pending',
            'is_meeting' => $isMeeting,
            'invite_token' => $inviteToken,
            'host_id' => $isMeeting ? $user->id : null, // PHASE 2: Set host for meetings
            'last_joined_at' => now(), // 24h link expiry: reset when anyone joins
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
            'caller_id'  => $call->caller_id,
            'callee_id'  => $call->callee_id,
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
     * POST /calls/group/{session}/joined (web, session auth)
     * Called when a participant (e.g. callee on web) has joined the LiveKit room.
     * Marks the call as started and broadcasts a signal so the caller (e.g. phone) can stop ringback and join LiveKit.
     */
    public function livekitJoined(Request $request, int $session)
    {
        $user = $request->user() ?? auth()->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $call = CallSession::find($session);
        if (!$call) {
            return response()->json(['status' => 'error', 'message' => 'Call not found'], 404);
        }

        if ($call->isLinkExpired()) {
            $call->update(['status' => 'ended', 'ended_at' => now()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Call link has expired (1 hour for 1:1 calls, 24 hours for group calls since last participant joined).',
            ], 410);
        }

        $allowed = $call->caller_id === (int) $user->id
            || $call->callee_id === (int) $user->id
            || ($call->group_id && $call->group && $call->group->isMember($user));

        if (!$allowed) {
            return response()->json(['status' => 'error', 'message' => 'Not a participant'], 403);
        }

        if (!$call->started_at) {
            $call->update([
                'status'   => 'ongoing',
                'started_at' => now(),
            ]);
        }
        // 24h link expiry: update last time someone joined
        $call->update(['last_joined_at' => now()]);

        $payload = json_encode(['type' => 'livekit-joined']);
        broadcast(new CallSignal($call, $payload))->toOthers();

        return response()->json(['status' => 'success']);
    }

    /**
     * GET /calls/group/{session} (web)
     * Show the call room. If this session is ended, redirect to the active call for the same group/conversation if one exists.
     */
    public function showCallRoom(Request $request, int $session)
    {
        $user = $request->user() ?? auth()->user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Please log in to join the call.');
        }

        $callSession = CallSession::find($session);
        if (!$callSession) {
            abort(404, 'Call not found');
        }

        // Authorize: caller, callee, or group member
        $allowed = $callSession->caller_id === (int) $user->id
            || $callSession->callee_id === (int) $user->id
            || ($callSession->group_id && $callSession->group && $callSession->group->isMember($user));
        if (!$allowed) {
            abort(403, 'You are not a participant in this call.');
        }

        // 24h link expiry: treat expired calls as ended
        if ($callSession->isLinkExpired()) {
            $callSession->update(['status' => 'ended', 'ended_at' => now()]);
        }

        // If this session is ended, try to redirect to the active call for the same group/conversation
        if (!in_array($callSession->status, ['pending', 'calling', 'ongoing'], true)) {
            $active = null;
            if ($callSession->group_id) {
                $active = CallSession::where('group_id', $callSession->group_id)
                    ->where('id', '!=', $session)
                    ->whereIn('status', ['pending', 'calling', 'ongoing'])
                    ->notExpired()
                    ->orderByDesc('created_at')
                    ->first();
            } else {
                $c1 = $callSession->caller_id;
                $c2 = $callSession->callee_id;
                $active = CallSession::whereNull('group_id')
                    ->whereIn('status', ['pending', 'calling', 'ongoing'])
                    ->notExpired()
                    ->where('id', '!=', $session)
                    ->where(function ($q) use ($c1, $c2) {
                        $q->where(function ($q2) use ($c1, $c2) {
                            $q2->where('caller_id', $c1)->where('callee_id', $c2);
                        })->orWhere(function ($q2) use ($c1, $c2) {
                            $q2->where('caller_id', $c2)->where('callee_id', $c1);
                        });
                    })
                    ->orderByDesc('created_at')
                    ->first();
            }
            if ($active) {
                return redirect()->to(url('/calls/group/' . $active->id . '?type=' . ($active->type ?? 'video')));
            }
            return view('calls.group_call', ['sessionId' => $session, 'ended' => true]);
        }

        return view('calls.group_call', ['sessionId' => $session, 'ended' => false]);
    }

    /**
     * GET /calls/{session}/status (web) or /api/v1/calls/{session}/status (API)
     * Returns whether the call session is still active (for restoring call UI on page load).
     */
    public function status(Request $request, CallSession $session)
    {
        $user = $request->user() ?? auth()->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }
        if ($session->group_id) {
            Gate::authorize('manage-group', $session->group);
        } else {
            if ($user->id !== $session->caller_id && $user->id !== $session->callee_id) {
                return response()->json(['status' => 'error', 'message' => 'Not authorized'], 403);
            }
        }
        $active = in_array($session->status, ['pending', 'ongoing'], true);
        return response()->json([
            'status'       => 'success',
            'call_status'  => $active ? 'active' : $session->status,
        ]);
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
        // Authorize: 1:1 = either party can end; group = only caller (who started it) or group admin can end
        if ($session->group_id) {
            $canEnd = ($session->caller_id === (int) $user->id)
                || ($session->group && $session->group->isAdmin($user));
            if (!$canEnd) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only the person who started the call or a group admin can end this call.',
                ], 403);
            }
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

        // Verify user is a participant (1:1: only invited caller/callee; group: members)
        if ($conversation) {
            if (!$conversation->isParticipant($user->id)) {
                $message = 'You must be invited to join this call.';
                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $message,
                    ], 403);
                }
                return redirect()->route('chat.index')->with('error', $message);
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
        if (!$callSession || !in_array($callSession->status, ['pending', 'calling', 'ongoing'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No active call found to join.'
                ], 404);
            }
            return redirect()->back()->with('error', 'No active call found to join.');
        }

        // 24h link expiry: if link has been idle 24h since last join, treat as ended
        if ($callSession->isLinkExpired()) {
            $callSession->update(['status' => 'ended', 'ended_at' => now()]);
            $expiredMessage = 'Call link has expired (1 hour for 1:1 calls, 24 hours for group calls since last participant joined).';
            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $expiredMessage], 410);
            }
            return redirect()->back()->with('error', $expiredMessage);
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

        // For regular web requests, redirect to the dedicated call room (same idea as live broadcast watch – visit link, see call)
        $callType = $callSession->type ?? 'video';
        $callRoomUrl = url('/calls/group/' . $callSession->id . '?type=' . $callType);
        return redirect($callRoomUrl);

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
            // Public (unauthenticated) response: STUN-only config.
            // We intentionally do NOT return TURN credentials unless authenticated.
            return response()->json([
                'status' => 'success',
                'config' => [
                    'stun' => [
                        ['urls' => 'stun:stun.l.google.com:19302'],
                        ['urls' => 'stun:stun1.l.google.com:19302'],
                    ],
                    'turn' => [],
                ],
                'improved_call_stack_enabled' => false,
            ]);
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