<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LiveBroadcast;
use App\Models\User;
use App\Services\LiveKitService;
use App\Services\PhaseModeService;
use App\Services\TestingModeService;
use App\Services\FeatureFlagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PHASE 2: Live Broadcast Controller
 * 
 * Handles live broadcasting with strict token issuance checks.
 * Username is REQUIRED for live broadcasts.
 */
class LiveBroadcastController extends Controller
{
    public function __construct(private LiveKitService $liveKitService)
    {
    }

    /**
     * Auto-end broadcasts that have been inactive for more than 5 minutes
     * This is called before starting a new broadcast to clean up old ones
     */
    private function autoEndInactiveBroadcasts()
    {
        // End broadcasts that started more than 5 minutes ago and have no recent activity
        // For now, we'll end broadcasts that are older than 5 minutes
        // In the future, we can check for actual stream activity via LiveKit API
        $inactiveBroadcasts = LiveBroadcast::where('status', 'live')
            ->where('started_at', '<', now()->subMinutes(5))
            ->get();
        
        foreach ($inactiveBroadcasts as $broadcast) {
            $broadcast->update([
                'status' => 'ended',
                'ended_at' => now(),
            ]);
        }
        
        return $inactiveBroadcasts->count();
    }

    /**
     * Get LiveKit token to start a live broadcast
     * POST /api/v1/live/start
     * 
     * CRITICAL: All checks must pass before token issuance
     */
    public function start(Request $request)
    {
        $user = $request->user();
        
        // 1. Authentication check
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // 2. Username check (REQUIRED for live broadcasts)
        if (!$user->username) {
            return response()->json([
                'message' => 'Set a username to enable Live broadcasting.',
                'requires_username' => true,
            ], 403);
        }

        // 3. Feature flag check
        if (!FeatureFlagService::isEnabled('live_broadcast', $user)) {
            return response()->json(['message' => 'Live broadcast feature is not available'], 403);
        }

        // 4. Phase Mode check
        if (!PhaseModeService::isFeatureAllowed('live_broadcast')) {
            return response()->json([
                'message' => 'Live broadcasting is not available in the current phase mode',
            ], 403);
        }

        // 5. Auto-end inactive broadcasts before checking limits
        $this->autoEndInactiveBroadcasts();
        
        // 6. Check max concurrent lives
        // Allow user to rejoin their own existing broadcast (check if they already have a live broadcast)
        $existingBroadcast = LiveBroadcast::where('status', 'live')
            ->where('broadcaster_id', $user->id)
            ->first();
        
        // If user already has a live broadcast, allow them to rejoin it instead of creating a new one
        if ($existingBroadcast) {
            // Generate token for existing broadcast
            $identity = $user->username ?? (string)$user->id;
            $token = $this->liveKitService->generateToken(
                $user->id,
                $existingBroadcast->room_name,
                $identity,
                [
                    'canPublish' => true,
                    'canSubscribe' => true,
                    'canPublishData' => true,
                    'recorder' => PhaseModeService::isRecordingEnabled() && $existingBroadcast->save_replay,
                ]
            );

            return response()->json([
                'status' => 'success',
                'broadcast_id' => $existingBroadcast->id,
                'broadcast_slug' => $existingBroadcast->slug,
                'room_name' => $existingBroadcast->room_name,
                'token' => $token,
                'websocket_url' => $this->liveKitService->getWebSocketUrl(),
                'is_existing' => true, // Flag to indicate this is an existing broadcast
            ]);
        }
        
        // 7. Check max concurrent lives for new broadcasts
        $isTestingMode = TestingModeService::isUserInTestingMode($user->id);
        $maxLives = $isTestingMode 
            ? TestingModeService::getTestingLimits()['max_lives'] ?? 1
            : PhaseModeService::getMaxConcurrentLives();

        $activeLives = LiveBroadcast::where('status', 'live')
            ->where('broadcaster_id', $user->id)
            ->count();

        if ($activeLives >= $maxLives) {
            return response()->json([
                'message' => "You have reached the maximum number of concurrent live broadcasts ({$maxLives})",
            ], 403);
        }

        // 8. Check global concurrent lives limit
        $globalActiveLives = LiveBroadcast::where('status', 'live')->count();
        $globalMax = $isTestingMode ? 3 : PhaseModeService::getMaxConcurrentLives();
        
        if ($globalActiveLives >= $globalMax) {
            return response()->json([
                'message' => 'Maximum number of concurrent live broadcasts reached',
            ], 403);
        }

        // 9. All checks passed - create broadcast session
        $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'save_replay' => ['nullable', 'boolean'],
        ]);

        $broadcast = LiveBroadcast::create([
            'broadcaster_id' => $user->id,
            'title' => $request->input('title'),
            'status' => 'live',
            'started_at' => now(),
            'save_replay' => $request->input('save_replay', false),
        ]);

        // Generate LiveKit JWT token for host (can publish)
        $identity = $user->username ?? (string)$user->id;
        $token = $this->liveKitService->generateToken(
            $user->id,
            $broadcast->room_name,
            $identity,
            [
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => true, // For live chat
                'recorder' => PhaseModeService::isRecordingEnabled() && $broadcast->save_replay,
            ]
        );

        return response()->json([
            'status' => 'success',
            'broadcast_id' => $broadcast->id,
            'broadcast_slug' => $broadcast->slug,
            'room_name' => $broadcast->room_name,
            'token' => $token,
            'websocket_url' => $this->liveKitService->getWebSocketUrl(),
            'is_broadcaster' => true, // Always true when creating a new broadcast
        ]);
    }

    /**
     * Get LiveKit token to join as viewer
     * POST /api/v1/live/{broadcastId}/join
     * 
     * Accepts both broadcast ID (int) and slug (string) for backward compatibility
     * Username NOT required for viewing
     */
    public function join(Request $request, $broadcastId)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Support both ID (int) and slug (string) for backward compatibility
        $broadcast = LiveBroadcast::findByIdentifier($broadcastId);
        
        if (!$broadcast) {
            return response()->json(['message' => 'Broadcast not found'], 404);
        }

        if ($broadcast->status !== 'live') {
            return response()->json(['message' => 'Broadcast is not live'], 404);
        }

        // Check if user is the broadcaster (owner)
        $isBroadcaster = $broadcast->broadcaster_id === $user->id;
        
        if ($isBroadcaster) {
            // Owner joining their own broadcast - give them broadcaster token
            $identity = $user->username ?? (string)$user->id;
            $token = $this->liveKitService->generateToken(
                $user->id,
                $broadcast->room_name,
                $identity,
                [
                    'canPublish' => true,
                    'canSubscribe' => true,
                    'canPublishData' => true, // For live chat
                    'recorder' => PhaseModeService::isRecordingEnabled() && $broadcast->save_replay,
                ]
            );

            return response()->json([
                'status' => 'success',
                'is_broadcaster' => true,
                'broadcast_id' => $broadcast->id,
                'broadcast_slug' => $broadcast->slug,
                'room_name' => $broadcast->room_name,
                'token' => $token,
                'websocket_url' => $this->liveKitService->getWebSocketUrl(),
            ]);
        }

        // Track viewer (only if not already tracking)
        $viewer = $broadcast->viewers()->firstOrCreate(
            [
                'broadcast_id' => $broadcast->id,
                'user_id' => $user->id,
            ],
            ['joined_at' => now()]
        );

        // Only increment if this is a new viewer (was just created)
        if ($viewer->wasRecentlyCreated) {
            $broadcast->increment('viewers_count');
        }

        // Generate token for viewer (subscribe only)
        $identity = $user->username ?? (string)$user->id;
        $token = $this->liveKitService->generateToken(
            $user->id,
            $broadcast->room_name,
            $identity,
            [
                'canPublish' => false,
                'canSubscribe' => true,
                'canPublishData' => true, // For live chat
            ]
        );

        return response()->json([
            'status' => 'success',
            'is_broadcaster' => false,
            'room_name' => $broadcast->room_name,
            'token' => $token,
            'websocket_url' => $this->liveKitService->getWebSocketUrl(),
        ]);
    }

    /**
     * End live broadcast
     * POST /api/v1/live/{broadcastSlug}/end
     */
    public function end(Request $request, $broadcastSlug)
    {
        $user = $request->user();
        $broadcast = LiveBroadcast::findByIdentifier($broadcastSlug);
        
        if (!$broadcast) {
            return response()->json(['message' => 'Broadcast not found'], 404);
        }

        // Only broadcaster can end
        if ($broadcast->broadcaster_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $broadcast->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        return response()->json(['status' => 'success']);
    }

    /**
     * Get a single live broadcast
     * GET /live-broadcast/{broadcastSlug}/info or /api/v1/live/{broadcastSlug}
     */
    public function show(Request $request, $broadcastSlug)
    {
        // Support both session and token auth
        $user = $request->user();
        if (!$user) {
            $user = \Illuminate\Support\Facades\Auth::user();
        }
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        $broadcast = LiveBroadcast::findByIdentifier($broadcastSlug);
        
        if (!$broadcast) {
            return response()->json(['message' => 'Broadcast not found'], 404);
        }
        
        // Load the broadcaster relationship
        $broadcast->load('broadcaster:id,name,username,avatar_path');

        return response()->json([
            'data' => [
                'id' => $broadcast->id,
                'slug' => $broadcast->slug,
                'title' => $broadcast->title,
                'description' => $broadcast->description,
                'broadcaster' => [
                    'id' => $broadcast->broadcaster->id,
                    'name' => $broadcast->broadcaster->name,
                    'username' => $broadcast->broadcaster->username,
                    'avatar_url' => $broadcast->broadcaster->avatar_url,
                ],
                'viewers_count' => $broadcast->viewers_count,
                'status' => $broadcast->status,
                'started_at' => $broadcast->started_at?->toIso8601String(),
                'ended_at' => $broadcast->ended_at?->toIso8601String(),
                'room_name' => $broadcast->room_name,
                'broadcaster_id' => $broadcast->broadcaster_id,
            ],
        ]);
    }

    /**
     * Get active live broadcasts (for World tab and Calls tab)
     * GET /api/v1/live/active
     */
    public function active(Request $request)
    {
        $broadcasts = LiveBroadcast::where('status', 'live')
            ->with('broadcaster:id,name,username,avatar_path')
            ->orderBy('started_at', 'desc')
            ->get();

        return response()->json([
            'data' => $broadcasts->map(function ($broadcast) {
                return [
                    'id' => $broadcast->id,
                    'slug' => $broadcast->slug,
                    'title' => $broadcast->title,
                    'broadcaster' => [
                        'id' => $broadcast->broadcaster->id,
                        'name' => $broadcast->broadcaster->name,
                        'username' => $broadcast->broadcaster->username,
                        'avatar_url' => $broadcast->broadcaster->avatar_url,
                    ],
                    'viewers_count' => $broadcast->viewers_count,
                    'started_at' => $broadcast->started_at->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Send chat message in live broadcast
     * POST /api/v1/live/{broadcastSlug}/chat
     */
    public function sendChat(Request $request, $broadcastSlug)
    {
        $user = $request->user();
        $broadcast = LiveBroadcast::findByIdentifier($broadcastSlug);
        
        if (!$broadcast) {
            return response()->json(['message' => 'Broadcast not found'], 404);
        }

        if ($broadcast->status !== 'live') {
            return response()->json(['message' => 'Broadcast is not live'], 404);
        }

        $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $chatMessage = $broadcast->chatMessages()->create([
            'user_id' => $user->id,
            'message' => $request->input('message'),
        ]);

        // Broadcast chat message event
        broadcast(new \App\Events\LiveBroadcastChatSent($chatMessage))->toOthers();

        return response()->json([
            'status' => 'success',
            'message' => $chatMessage,
        ]);
    }
}
