<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LiveBroadcast;
use App\Models\LiveBroadcastGift;
use App\Models\User;
use App\Services\LiveKitService;
use App\Services\PhaseModeService;
use App\Services\TestingModeService;
use App\Services\FeatureFlagService;
use App\Services\Sika\SikaWalletService;
use App\Events\LiveBroadcastStarted;
use App\Events\LiveBroadcastEnded;
use App\Events\LiveBroadcastGiftSent;
use App\Services\WorldFeedActivityService;
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
    public function __construct(
        private LiveKitService $liveKitService,
        private WorldFeedActivityService $worldFeedActivityService,
        private SikaWalletService $sikaWalletService
    ) {
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
            
            // Broadcast event for each ended broadcast
            broadcast(new LiveBroadcastEnded($broadcast));
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
        // Accept JSON or form data
        if ($request->isJson() || $request->expectsJson()) {
            $request->validate([
                'title' => ['nullable', 'string', 'max:200'],
                'save_replay' => ['nullable', 'boolean'],
            ]);
        } else {
            $request->validate([
                'title' => ['nullable', 'string', 'max:200'],
                'save_replay' => ['nullable', 'boolean'],
            ]);
        }

        // Generate default title if not provided: "{username} is live" or "user_{id} is live"
        $title = $request->input('title');
        if (empty($title)) {
            $username = $user->username ?? "user_{$user->id}";
            $title = "$username is live";
        }

        $broadcast = LiveBroadcast::create([
            'broadcaster_id' => $user->id,
            'title' => $title,
            'status' => 'live',
            'started_at' => now(),
            'save_replay' => $request->input('save_replay', false),
        ]);

        // Broadcast event to notify all users about the new live broadcast
        broadcast(new LiveBroadcastStarted($broadcast));

        // Instagram/TikTok-style: activity feed + push for followers
        $this->worldFeedActivityService->onLiveStarted($broadcast);

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

        $rawUrl = $this->liveKitService->getWebSocketUrl();
        $websocketUrl = $this->ensureWssForSecureRequest($rawUrl);
        \Illuminate\Support\Facades\Log::info('LiveKit join (broadcaster)', [
            'broadcast_id' => $broadcast->id,
            'room_name' => $broadcast->room_name,
            'websocket_url_raw' => $rawUrl,
            'websocket_url_final' => $websocketUrl,
            'request_secure' => request()->secure(),
            'x_forwarded_proto' => request()->header('X-Forwarded-Proto'),
        ]);
        return response()->json([
            'status' => 'success',
            'is_broadcaster' => true,
            'broadcast_id' => $broadcast->id,
            'broadcast_slug' => $broadcast->slug,
            'room_name' => $broadcast->room_name,
            'token' => $token,
            'websocket_url' => $websocketUrl,
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

        $rawUrl = $this->liveKitService->getWebSocketUrl();
        $websocketUrl = $this->ensureWssForSecureRequest($rawUrl);
        \Illuminate\Support\Facades\Log::info('LiveKit join (viewer)', [
            'broadcast_id' => $broadcast->id,
            'room_name' => $broadcast->room_name,
            'websocket_url_raw' => $rawUrl,
            'websocket_url_final' => $websocketUrl,
            'request_secure' => request()->secure(),
            'x_forwarded_proto' => request()->header('X-Forwarded-Proto'),
        ]);
        return response()->json([
            'status' => 'success',
            'is_broadcaster' => false,
            'room_name' => $broadcast->room_name,
            'token' => $token,
            'websocket_url' => $websocketUrl,
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

        // Broadcast event to notify all users that the broadcast has ended
        broadcast(new LiveBroadcastEnded($broadcast));

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
     * Creator analytics dashboard: stats and recent broadcasts for the authenticated user.
     * GET /api/v1/live/creator/analytics
     */
    public function creatorAnalytics(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $broadcasts = LiveBroadcast::where('broadcaster_id', $user->id)
            ->whereIn('status', ['live', 'ended'])
            ->orderBy('started_at', 'desc')
            ->get();

        $totalBroadcasts = $broadcasts->count();
        $totalViews = $broadcasts->sum('viewers_count');
        $totalMinutes = 0;
        foreach ($broadcasts as $b) {
            if ($b->started_at && $b->ended_at) {
                $totalMinutes += (int) $b->started_at->diffInMinutes($b->ended_at);
            }
        }

        $recent = $broadcasts->take(20)->map(function ($b) {
            $durationMinutes = 0;
            if ($b->started_at && $b->ended_at) {
                $durationMinutes = (int) $b->started_at->diffInMinutes($b->ended_at);
            }
            return [
                'id' => $b->id,
                'slug' => $b->slug,
                'title' => $b->title,
                'status' => $b->status,
                'viewers_count' => $b->viewers_count,
                'started_at' => $b->started_at?->toIso8601String(),
                'ended_at' => $b->ended_at?->toIso8601String(),
                'duration_minutes' => $durationMinutes,
            ];
        })->values();

        return response()->json([
            'data' => [
                'total_broadcasts' => $totalBroadcasts,
                'total_views' => $totalViews,
                'total_minutes' => $totalMinutes,
                'recent_broadcasts' => $recent,
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

    /**
     * Get available gift types
     * GET /api/v1/live/gifts/types
     */
    public function giftTypes()
    {
        $types = collect(LiveBroadcastGift::getAllGiftTypes())->map(function ($info, $type) {
            return [
                'type' => $type,
                'coins' => $info['coins'],
                'emoji' => $info['emoji'],
                'label' => $info['label'],
            ];
        })->values();

        return response()->json(['data' => $types]);
    }

    /**
     * Send a gift during live broadcast
     * POST /api/v1/live/{broadcastSlug}/gift
     */
    public function sendGift(Request $request, $broadcastSlug)
    {
        $user = $request->user();
        $broadcast = LiveBroadcast::findByIdentifier($broadcastSlug);
        
        if (!$broadcast) {
            return response()->json(['message' => 'Broadcast not found'], 404);
        }

        if ($broadcast->status !== 'live') {
            return response()->json(['message' => 'Broadcast is not live'], 404);
        }

        // Can't send gift to yourself
        if ($broadcast->broadcaster_id === $user->id) {
            return response()->json(['message' => 'Cannot send gift to yourself'], 422);
        }

        $request->validate([
            'gift_type' => ['required', 'string', 'in:' . implode(',', array_keys(LiveBroadcastGift::GIFT_TYPES))],
            'message' => ['nullable', 'string', 'max:200'],
        ]);

        $giftType = $request->input('gift_type');
        $giftInfo = LiveBroadcastGift::getGiftInfo($giftType);
        
        if (!$giftInfo) {
            return response()->json(['message' => 'Invalid gift type'], 422);
        }

        $coins = $giftInfo['coins'];

        // Transfer coins using Sika wallet
        try {
            $idempotencyKey = 'live_gift_' . $user->id . '_' . $broadcast->id . '_' . now()->timestamp;
            
            $this->sikaWalletService->gift(
                fromUserId: $user->id,
                toUserId: $broadcast->broadcaster_id,
                coins: $coins,
                idempotencyKey: $idempotencyKey,
                note: "Live gift: {$giftInfo['label']} {$giftInfo['emoji']}"
            );
        } catch (\App\Exceptions\Sika\SikaWalletException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        // Record the gift
        $gift = LiveBroadcastGift::create([
            'broadcast_id' => $broadcast->id,
            'sender_id' => $user->id,
            'receiver_id' => $broadcast->broadcaster_id,
            'gift_type' => $giftType,
            'coins' => $coins,
            'message' => $request->input('message'),
        ]);

        // Update broadcast totals
        $broadcast->increment('gifts_count');
        $broadcast->increment('gifts_total', $coins);

        // Broadcast gift event for real-time display
        broadcast(new LiveBroadcastGiftSent($gift))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Gift sent successfully',
            'gift' => [
                'id' => $gift->id,
                'gift_type' => $giftType,
                'coins' => $coins,
                'emoji' => $giftInfo['emoji'],
                'label' => $giftInfo['label'],
            ],
        ]);
    }

    /**
     * Get gifts for a broadcast (for replay or stats)
     * GET /api/v1/live/{broadcastSlug}/gifts
     */
    public function getGifts(Request $request, $broadcastSlug)
    {
        $broadcast = LiveBroadcast::findByIdentifier($broadcastSlug);
        
        if (!$broadcast) {
            return response()->json(['message' => 'Broadcast not found'], 404);
        }

        $gifts = LiveBroadcastGift::where('broadcast_id', $broadcast->id)
            ->with('sender:id,name,username,avatar_path')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 50));

        $items = $gifts->getCollection()->map(function ($gift) {
            $giftInfo = LiveBroadcastGift::getGiftInfo($gift->gift_type);
            return [
                'id' => $gift->id,
                'gift_type' => $gift->gift_type,
                'coins' => $gift->coins,
                'emoji' => $giftInfo['emoji'] ?? '🎁',
                'label' => $giftInfo['label'] ?? 'Gift',
                'message' => $gift->message,
                'sender' => [
                    'id' => $gift->sender->id,
                    'name' => $gift->sender->name,
                    'username' => $gift->sender->username,
                    'avatar_url' => $gift->sender->avatar_url,
                ],
                'created_at' => $gift->created_at->toIso8601String(),
            ];
        });
        $gifts->setCollection($items);

        return response()->json([
            'data' => $gifts->items(),
            'pagination' => [
                'current_page' => $gifts->currentPage(),
                'last_page' => $gifts->lastPage(),
                'per_page' => $gifts->perPage(),
                'total' => $gifts->total(),
            ],
            'summary' => [
                'total_gifts' => $broadcast->gifts_count,
                'total_coins' => $broadcast->gifts_total,
            ],
        ]);
    }

    /**
     * If the current request is over HTTPS (or behind a proxy that set X-Forwarded-Proto),
     * return wss:// so the browser does not block mixed content.
     */
    private function ensureWssForSecureRequest(string $url): string
    {
        if (!str_starts_with($url, 'ws://')) {
            return $url;
        }
        if (request()->secure() || request()->header('X-Forwarded-Proto') === 'https') {
            return 'wss://' . substr($url, 5);
        }
        return $url;
    }
}
