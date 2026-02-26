<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BroadcastList;
use App\Models\UploadSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class BroadcastListController extends Controller
{
    /**
     * Get broadcast settings/limits for the client.
     * GET /broadcast-lists/settings
     */
    public function settings(Request $request)
    {
        $user = $request->user();
        $settings = UploadSetting::getBroadcastSettings();
        
        // Check if user is admin (for admin_only restriction display)
        $settings['user_is_admin'] = $user->is_admin ?? false;
        $settings['can_broadcast'] = !$settings['admin_only'] || $settings['user_is_admin'];
        
        // Get user's broadcast count for today
        $todayCount = $this->getUserBroadcastCountToday($user->id);
        $settings['broadcasts_sent_today'] = $todayCount;
        $settings['broadcasts_remaining_today'] = $settings['max_messages_per_day'] > 0 
            ? max(0, $settings['max_messages_per_day'] - $todayCount)
            : -1; // -1 means unlimited

        return response()->json(['data' => $settings]);
    }

    /**
     * Get the number of broadcasts sent by user today.
     */
    private function getUserBroadcastCountToday(int $userId): int
    {
        $cacheKey = "broadcast_count:{$userId}:" . now()->format('Y-m-d');
        return Cache::get($cacheKey, 0);
    }

    /**
     * Increment the broadcast count for user today.
     */
    private function incrementUserBroadcastCount(int $userId): void
    {
        $cacheKey = "broadcast_count:{$userId}:" . now()->format('Y-m-d');
        $current = Cache::get($cacheKey, 0);
        // Cache until end of day
        $ttl = now()->endOfDay()->diffInSeconds(now());
        Cache::put($cacheKey, $current + 1, $ttl);
    }

    /**
     * Get all broadcast lists for the authenticated user.
     * GET /broadcast-lists
     */
    public function index(Request $request)
    {
        $lists = $request->user()
            ->broadcastLists()
            ->with(['recipients:id,name,phone,avatar_path'])
            ->latest()
            ->get();

        $data = $lists->map(function ($list) {
            return [
                'id' => $list->id,
                'name' => $list->name,
                'description' => $list->description,
                'recipient_count' => $list->recipients()->count(),
                'recipients' => $list->recipients->map(function ($recipient) {
                    return [
                        'id' => $recipient->id,
                        'name' => $recipient->name,
                        'phone' => $recipient->phone,
                        'avatar_url' => $recipient->avatar_path 
                            ? asset('storage/' . $recipient->avatar_path) 
                            : null,
                    ];
                }),
                'created_at' => $list->created_at->toIso8601String(),
                'updated_at' => $list->updated_at->toIso8601String(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Create a new broadcast list.
     * POST /broadcast-lists
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        // Check if broadcast is admin-only
        if (UploadSetting::getBroadcastAdminOnly() && !($user->is_admin ?? false)) {
            return response()->json([
                'message' => 'Broadcast feature is restricted to administrators only.',
            ], 403);
        }

        $maxRecipients = UploadSetting::getBroadcastMaxRecipients();
        
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'recipients' => "required|array|min:1|max:{$maxRecipients}",
            'recipients.*' => 'exists:users,id',
        ]);

        return DB::transaction(function () use ($request, $data) {
            $list = BroadcastList::create([
                'user_id' => $request->user()->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $list->recipients()->attach($data['recipients']);

            $list->load(['recipients:id,name,phone,avatar_path']);

            return response()->json([
                'data' => [
                    'id' => $list->id,
                    'name' => $list->name,
                    'description' => $list->description,
                    'recipient_count' => $list->recipients->count(),
                    'recipients' => $list->recipients->map(function ($recipient) {
                        return [
                            'id' => $recipient->id,
                            'name' => $recipient->name,
                            'phone' => $recipient->phone,
                            'avatar_url' => $recipient->avatar_path 
                                ? asset('storage/' . $recipient->avatar_path) 
                                : null,
                        ];
                    }),
                    'created_at' => $list->created_at->toIso8601String(),
                    'updated_at' => $list->updated_at->toIso8601String(),
                ],
            ], 201);
        });
    }

    /**
     * Get a specific broadcast list.
     * GET /broadcast-lists/{id}
     */
    public function show(Request $request, $id)
    {
        $list = $request->user()
            ->broadcastLists()
            ->with(['recipients:id,name,phone,avatar_path'])
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $list->id,
                'name' => $list->name,
                'description' => $list->description,
                'recipient_count' => $list->recipients->count(),
                'recipients' => $list->recipients->map(function ($recipient) {
                    return [
                        'id' => $recipient->id,
                        'name' => $recipient->name,
                        'phone' => $recipient->phone,
                        'avatar_url' => $recipient->avatar_path 
                            ? asset('storage/' . $recipient->avatar_path) 
                            : null,
                    ];
                }),
                'created_at' => $list->created_at->toIso8601String(),
                'updated_at' => $list->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get members (recipients) of a broadcast list.
     * GET /broadcast-lists/{id}/members
     * Used by mobile sync to store members locally.
     */
    public function members(Request $request, $id)
    {
        $list = $request->user()
            ->broadcastLists()
            ->with(['recipients:id,name,phone,avatar_path'])
            ->findOrFail($id);

        $data = $list->recipients->map(function ($recipient) {
            $pivot = $recipient->pivot;
            return [
                'contact_id' => $recipient->id,
                'phone' => $recipient->phone,
                'added_at' => $pivot && $pivot->created_at
                    ? $pivot->created_at->toIso8601String()
                    : now()->toIso8601String(),
            ];
        })->values()->all();

        return response()->json(['data' => $data]);
    }

    /**
     * Update a broadcast list.
     * PUT /broadcast-lists/{id}
     */
    public function update(Request $request, $id)
    {
        $list = $request->user()->broadcastLists()->findOrFail($id);
        $maxRecipients = UploadSetting::getBroadcastMaxRecipients();

        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'recipients' => "sometimes|array|min:1|max:{$maxRecipients}",
            'recipients.*' => 'exists:users,id',
        ]);

        return DB::transaction(function () use ($list, $data) {
            if (isset($data['name'])) {
                $list->name = $data['name'];
            }
            if (isset($data['description'])) {
                $list->description = $data['description'];
            }
            $list->save();

            if (isset($data['recipients'])) {
                $list->recipients()->sync($data['recipients']);
            }

            $list->load(['recipients:id,name,phone,avatar_path']);

            return response()->json([
                'data' => [
                    'id' => $list->id,
                    'name' => $list->name,
                    'description' => $list->description,
                    'recipient_count' => $list->recipients->count(),
                    'recipients' => $list->recipients->map(function ($recipient) {
                        return [
                            'id' => $recipient->id,
                            'name' => $recipient->name,
                            'phone' => $recipient->phone,
                            'avatar_url' => $recipient->avatar_path 
                                ? asset('storage/' . $recipient->avatar_path) 
                                : null,
                        ];
                    }),
                    'created_at' => $list->created_at->toIso8601String(),
                    'updated_at' => $list->updated_at->toIso8601String(),
                ],
            ]);
        });
    }

    /**
     * Delete a broadcast list.
     * DELETE /broadcast-lists/{id}
     */
    public function destroy(Request $request, $id)
    {
        $list = $request->user()->broadcastLists()->findOrFail($id);
        $list->delete();

        return response()->json(['message' => 'Broadcast list deleted']);
    }

    /**
     * Send a message to all recipients in a broadcast list.
     * POST /broadcast-lists/{id}/send
     */
    public function sendMessage(Request $request, $id)
    {
        $sender = $request->user();
        
        // Check if broadcast is admin-only
        if (UploadSetting::getBroadcastAdminOnly() && !($sender->is_admin ?? false)) {
            return response()->json([
                'message' => 'Broadcast feature is restricted to administrators only.',
            ], 403);
        }

        // Check daily limit
        $maxPerDay = UploadSetting::getBroadcastMaxMessagesPerDay();
        if ($maxPerDay > 0) {
            $todayCount = $this->getUserBroadcastCountToday($sender->id);
            if ($todayCount >= $maxPerDay) {
                return response()->json([
                    'message' => "Daily broadcast limit reached ({$maxPerDay} messages per day).",
                    'broadcasts_sent_today' => $todayCount,
                    'limit' => $maxPerDay,
                ], 429);
            }
        }

        // Get attachment settings
        $attachmentsEnabled = UploadSetting::getBroadcastAttachmentsEnabled();
        $maxAttachments = UploadSetting::getBroadcastMaxAttachments();

        $list = $sender->broadcastLists()->with('recipients')->findOrFail($id);

        // Build validation rules based on settings
        $rules = [
            'body' => 'nullable|string',
        ];

        if ($attachmentsEnabled) {
            $rules['attachments'] = "nullable|array|max:{$maxAttachments}";
            $rules['attachments.*'] = 'integer|exists:attachments,id';
        }

        $data = $request->validate($rules);

        // If attachments are disabled but user tried to send them
        if (!$attachmentsEnabled && !empty($request->input('attachments'))) {
            return response()->json([
                'message' => 'Attachments are not allowed in broadcast messages.',
            ], 422);
        }

        // Ensure there's content to send
        if (empty($data['body']) && empty($data['attachments'])) {
            return response()->json([
                'message' => 'Message body or attachments are required.',
            ], 422);
        }

        $recipients = $list->recipients;
        $sentMessages = [];

        DB::beginTransaction();
        try {
            foreach ($recipients as $recipient) {
                // Find or create conversation with each recipient
                $conversation = \App\Models\Conversation::findOrCreateDirect($sender->id, $recipient->id);

                // Create message
                $message = \App\Models\Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->id,
                    'body' => $data['body'] ?? null,
                ]);

                // Attach attachments if provided and enabled
                if ($attachmentsEnabled && !empty($data['attachments'])) {
                    $message->attachments()->attach($data['attachments']);
                }

                $sentMessages[] = [
                    'recipient_id' => $recipient->id,
                    'recipient_name' => $recipient->name,
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                ];
            }

            // Increment daily broadcast count
            $this->incrementUserBroadcastCount($sender->id);

            DB::commit();

            return response()->json([
                'message' => 'Message sent to ' . count($sentMessages) . ' recipients',
                'sent_messages' => $sentMessages,
                'broadcasts_remaining_today' => $maxPerDay > 0 
                    ? max(0, $maxPerDay - $this->getUserBroadcastCountToday($sender->id))
                    : -1,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to send broadcast: ' . $e->getMessage(),
            ], 500);
        }
    }
}

