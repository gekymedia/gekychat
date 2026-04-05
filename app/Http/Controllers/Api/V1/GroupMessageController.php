<?php
namespace App\Http\Controllers\Api\V1;

use App\Events\GroupMessageDeleted;
use App\Events\GroupMessageReadEvent;
use App\Events\GroupMessageSent;
use App\Events\TypingInGroup;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Http\Responses\ErrorResponse;
use App\Models\Attachment;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\GroupPinnedMessage;
use App\Services\TextFormattingService;
use App\Services\MentionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class GroupMessageController extends Controller
{
    protected $mentionService;
    
    public function __construct(MentionService $mentionService)
    {
        $this->mentionService = $mentionService;
    }
    /**
     * Get messages in a group with pagination
     * GET /api/v1/groups/{id}/messages
     * Query: limit, before (date), after (date), after_id (message id for delta sync)
     */
    public function index(Request $r, $groupId)
    {
        $r->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'before' => 'nullable|date',
            'after' => 'nullable|date',
            'after_id' => 'nullable|integer|min:0',
            'page' => 'nullable|integer|min:1',
        ]);

        $group = Group::findOrFail($groupId);
        abort_unless($group->isMember($r->user()), 403);

        $uid = $r->user()->id;
        $limit = $r->input('limit', 50);

        $query = $group->messages()
            ->with(['sender:id,name,phone,avatar_path', 'attachments', 'replyTo', 'forwardedFrom', 'reactions.user'])
            ->visibleTo($uid)
            ->orderBy('created_at', 'desc');

        if ($r->filled('after_id')) {
            $query->where('id', '>', (int) $r->after_id);
        }

        if ($r->filled('before')) {
            $query->where('created_at', '<', $r->before);
        }

        if ($r->filled('after') && !$r->filled('after_id')) {
            $query->where('created_at', '>', $r->after);
        }

        $messages = $query->limit($limit)->get()->sortBy('created_at')->values();

        return response()->json([
            'data' => MessageResource::collection($messages),
        ]);
    }

    public function store(Request $r, $groupId)
    {
        // Normalize empty reply_to / reply_to_id so validation passes (mobile may send "")
        $replyToRaw = $r->input('reply_to_id') ?? $r->input('reply_to');
        if ($replyToRaw !== null && $replyToRaw !== '' && (string) $replyToRaw !== '0') {
            $r->merge(['reply_to' => (int) $replyToRaw]);
        } elseif ($replyToRaw === '' || $replyToRaw === null) {
            $r->merge(['reply_to' => null]);
        }
        $r->validate([
            'body' => 'nullable|string|max:5000',
            'reply_to' => 'nullable|integer|exists:group_messages,id',
            'forward_from_id' => 'nullable|integer|exists:group_messages,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'integer|exists:attachments,id',
            'expires_in' => 'nullable|integer|min:0|max:2160', // Disappearing message timer (hours), same as 1:1; 2160 = 90 days
        ]);
        
        // Validate text formatting if body is provided
        if ($r->filled('body')) {
            $validation = TextFormattingService::validateFormatting($r->body);
            if (!$validation['valid']) {
                return ErrorResponse::validation(['formatting' => $validation['errors']]);
            }
        }

        $g = Group::findOrFail($groupId);
        abort_unless($g->isMember($r->user()), 403);
        
        // Check message lock: only admins can send when enabled (like WhatsApp)
        if ($g->message_lock && !Gate::allows('send-group-message', $g)) {
            return ErrorResponse::forbidden('Only admins can send messages in this group. The group has been locked.');
        }

        if (!$r->filled('body') && !$r->filled('attachments') && !$r->filled('forward_from_id')) {
            return ErrorResponse::create(ErrorResponse::ERROR_INVALID_REQUEST, 'Please enter a message, attach a file, or forward a message.', null, 422);
        }

        $fwdChain = null;
        if ($r->filled('forward_from_id')) {
            $orig = GroupMessage::with('sender')->find($r->forward_from_id);
            $fwdChain = $orig ? $orig->buildForwardChain() : null;
        }

        $expiresAt = $r->filled('expires_in') && (int) $r->expires_in > 0
            ? now()->addHours((int) $r->expires_in)
            : null;

        $replyTo = $r->filled('reply_to') ? (int) $r->reply_to : null;
        $m = $g->messages()->create([
            'sender_id' => $r->user()->id,
            'body' => (string)($r->body ?? ''),
            'type' => $r->input('type'), // Client-sent type so server doesn't misclassify (e.g. voice vs document)
            'reply_to' => $replyTo,
            'forwarded_from_id' => $r->forward_from_id,
            'forward_chain' => $fwdChain,
            'delivered_at' => now(),
            'is_view_once' => (bool)$r->input('view_once', false),
            'expires_at' => $expiresAt,
        ]);

        if ($r->filled('attachments')) {
            Attachment::whereIn('id',$r->attachments)->update(['attachable_id'=>$m->id,'attachable_type'=>GroupMessage::class]);
        }

        // NEW: Process @mentions in message body
        if (!empty($m->body)) {
            try {
                $mentionsCreated = $this->mentionService->createMentions(
                    $m,
                    $r->user()->id,
                    $groupId // pass group ID for validation
                );
                
                if ($mentionsCreated > 0) {
                    Log::info("Created {$mentionsCreated} mentions in group message #{$m->id}");
                    // Reload to include mentions in response
                    $m->load('mentions.mentionedUser:id,name,username,avatar_path');
                }
            } catch (\Exception $e) {
                Log::error('Error processing mentions in group', [
                    'message_id' => $m->id,
                    'group_id' => $groupId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $m->load(['sender','attachments','replyTo','forwardedFrom','reactions.user']);
        broadcast(new GroupMessageSent($m))->toOthers();

        return response()->json(['data' => new MessageResource($m)], 201);
    }

    /**
     * Get group message info (readers, delivered, sent status) - WhatsApp style
     * GET /api/v1/group-messages/{id}/info
     */
    public function info(Request $r, $messageId)
    {
        $message = GroupMessage::findOrFail($messageId);
        $userId = $r->user()->id;
        
        // Only sender can see message info
        abort_unless($message->sender_id === $userId, 403, 'Only message sender can view message info');
        
        // Check if user is member of group
        abort_unless($message->group->isMember($r->user()), 403);
        
        // Get all statuses with user info
        $statuses = $message->statuses()
            ->with('user:id,name,avatar_path')
            ->get();
        
        // Get group members to know total count (excluding sender)
        $group = $message->group;
        $members = $group->members()->where('users.id', '!=', $userId)->pluck('users.id');
        $totalRecipients = $members->count();
        
        // Group statuses by type
        $sent = $statuses->where('status', \App\Models\GroupMessageStatus::STATUS_SENT)->values();
        $delivered = $statuses->where('status', \App\Models\GroupMessageStatus::STATUS_DELIVERED)->values();
        $read = $statuses->where('status', \App\Models\GroupMessageStatus::STATUS_READ)->values();
        
        return response()->json([
            'message_id' => $message->id,
            'created_at' => $message->created_at->toIso8601String(),
            'total_recipients' => $totalRecipients,
            'sent' => [
                'count' => $sent->count(),
                'users' => $sent->map(function ($status) {
                    return [
                        'user_id' => $status->user->id,
                        'user_name' => $status->user->name,
                        'user_avatar' => $status->user->avatar_path ? asset('storage/' . $status->user->avatar_path) : null,
                        'updated_at' => $status->updated_at->toIso8601String(),
                    ];
                }),
            ],
            'delivered' => [
                'count' => $delivered->count(),
                'users' => $delivered->map(function ($status) {
                    return [
                        'user_id' => $status->user->id,
                        'user_name' => $status->user->name,
                        'user_avatar' => $status->user->avatar_path ? asset('storage/' . $status->user->avatar_path) : null,
                        'updated_at' => $status->updated_at->toIso8601String(),
                    ];
                }),
            ],
            'read' => [
                'count' => $read->count(),
                'users' => $read->map(function ($status) {
                    return [
                        'user_id' => $status->user->id,
                        'user_name' => $status->user->name,
                        'user_avatar' => $status->user->avatar_path ? asset('storage/' . $status->user->avatar_path) : null,
                        'updated_at' => $status->updated_at->toIso8601String(),
                    ];
                }),
            ],
        ]);
    }

    public function markRead(Request $r, $messageId)
    {
        $m = GroupMessage::findOrFail($messageId);
        abort_unless($m->group->isMember($r->user()), 403);
        $m->markAsReadFor($r->user()->id);
        
        // Check privacy setting: if user has disable_read_receipts enabled, don't broadcast
        if (PrivacyService::shouldSendReadReceipt($r->user())) {
            broadcast(new GroupMessageReadEvent($m->group_id, $m->id, $r->user()->id))->toOthers();
        }
        
        return response()->json(['ok'=>true]);
    }

    public function typing(Request $r, $groupId)
    {
        $r->validate(['is_typing'=>'required|boolean']);
        $g = Group::findOrFail($groupId);
        abort_unless($g->isMember($r->user()), 403);
        
        // Check privacy setting: if user has hide_typing enabled, don't broadcast
        if (!PrivacyService::shouldBroadcastTyping($r->user())) {
            // User has typing privacy enabled, return success but don't broadcast
            return response()->json(['ok'=>true, 'broadcasted'=>false]);
        }
        
        broadcast(new TypingInGroup((int)$groupId, $r->user()->id, (bool)$r->is_typing))->toOthers();
        return response()->json(['ok'=>true, 'broadcasted'=>true]);
    }

    public function forwardToTargets(Request $r, $messageId)
    {
        $r->validate([
            'targets' => 'required|array|min:1',
            'targets.*.type' => 'required|in:group,conversation',
            'targets.*.id' => 'required|integer',
        ]);

        $msg = GroupMessage::with(['sender','attachments','group.members'])->findOrFail($messageId);
        abort_unless($msg->group->isMember($r->user()), 403);

        $results = app('App\\Services\\ForwardService')->forwardGroupToTargets($msg, $r->user(), $r->targets);
        return response()->json(['status'=>'success','results'=>$results]);
    }

    /**
     * Share location in a group
     * POST /api/v1/groups/{id}/share-location
     */
    public function shareLocation(Request $r, $groupId)
    {
        $r->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
            'place_name' => 'nullable|string|max:255',
        ]);

        $group = Group::findOrFail($groupId);
        abort_unless($group->isMember($r->user()), 403);

        $locationData = [
            'type' => 'location',
            'latitude' => $r->latitude,
            'longitude' => $r->longitude,
            'address' => $r->address,
            'place_name' => $r->place_name,
            'shared_at' => now()->toISOString(),
        ];

        $message = GroupMessage::create([
            'group_id' => $group->id,
            'sender_id' => $r->user()->id,
            'body' => '📍 Shared location',
            'type' => 'location',
            'location_data' => $locationData,
        ]);

        $message->load(['sender', 'attachments']);

        broadcast(new GroupMessageSent($message))->toOthers();

        return response()->json([
            'success' => true,
            'data' => new MessageResource($message),
        ]);
    }

    /**
     * Share contact in a group
     * POST /api/v1/groups/{id}/share-contact
     * 
     * Accepts either:
     * - contact_id (existing contact in database)
     * - OR direct contact data (name, phone, email) for mobile apps
     */
    public function shareContact(Request $r, $groupId)
    {
        $group = Group::findOrFail($groupId);
        abort_unless($group->isMember($r->user()), 403);

        // Support both contact_id (web) and direct contact data (mobile)
        if ($r->filled('contact_id')) {
            // Web version: use existing contact from database
            $r->validate(['contact_id' => 'required|exists:contacts,id']);
            
            $contact = \App\Models\Contact::where('id', $r->contact_id)
                ->where('user_id', $r->user()->id)
                ->firstOrFail();

            $contactUser = \App\Models\User::where('phone', $contact->phone)->first();

            $contactData = [
                'type' => 'contact',
                'contact_id' => $contact->id,
                'display_name' => $contact->display_name ?? $contact->phone,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'user_id' => $contactUser?->id,
                'shared_at' => now()->toISOString(),
            ];
        } else {
            // Mobile version: accept direct contact data from device
            $r->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|string|max:255',
            ]);

            // Check if this phone number belongs to a registered user
            $contactUser = \App\Models\User::where('phone', $r->phone)->first();

            $contactData = [
                'type' => 'contact',
                'display_name' => $r->name,
                'phone' => $r->phone,
                'email' => $r->email,
                'user_id' => $contactUser?->id,
                'shared_at' => now()->toISOString(),
            ];
        }

        $message = GroupMessage::create([
            'group_id' => $group->id,
            'sender_id' => $r->user()->id,
            'body' => '👤 Shared contact',
            'type' => 'contact',
            'contact_data' => $contactData,
        ]);

        $message->load(['sender', 'attachments']);

        broadcast(new GroupMessageSent($message))->toOthers();

        return response()->json([
            'success' => true,
            'data' => new MessageResource($message),
        ]);
    }

    /**
     * Search messages within a group.
     * GET /groups/{id}/search?q=
     */
    public function search(Request $request, $groupId)
    {
        $request->validate([
            'q' => 'required|string|min:1',
        ]);

        $group = Group::findOrFail($groupId);
        abort_unless($group->isMember($request->user()), 403);

        $query = $request->input('q');
        $messages = $group->messages()
            ->where(function ($q) use ($query) {
                $q->where('body', 'LIKE', "%{$query}%")
                  ->orWhereHas('attachments', function ($a) use ($query) {
                      $a->where('original_name', 'LIKE', "%{$query}%");
                  });
            })
            ->with(['sender:id,name,avatar_path', 'attachments'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => MessageResource::collection($messages),
            'query' => $query,
            'total' => $messages->count(),
        ]);
    }

    /**
     * Reply privately to a group message
     * POST /api/v1/groups/{groupId}/messages/{messageId}/reply-private
     */
    public function replyPrivate(Request $r, $groupId, $messageId)
    {
        $group = Group::findOrFail($groupId);
        abort_unless($group->isMember($r->user()), 403);

        $message = GroupMessage::findOrFail($messageId);
        $message->loadMissing('sender');
        $sender = $message->sender;

        if (!$sender) {
            return ErrorResponse::notFound('Original sender');
        }

        $currentUser = $r->user();

        // Find or create direct conversation
        if ($sender->id === $currentUser->id) {
            $conversation = \App\Models\Conversation::findOrCreateSavedMessages($currentUser->id);
        } else {
            $conversation = \App\Models\Conversation::findOrCreateDirect($currentUser->id, $sender->id);
        }

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'group_id' => $group->id,
                'group_name' => $group->name,
                'group_message_id' => $message->id,
                'group_message_body' => $message->body,
                'group_message_sender' => $sender->name ?? $sender->phone,
            ]
        ]);
    }

    /**
     * POST /api/v1/groups/{id}/polls
     * Send a poll as a group message.
     * Body: { question, options: [string], allow_multiple?, is_anonymous?, closes_at? }
     */
    public function sendPoll(Request $r, $groupId)
    {
        $group = Group::findOrFail($groupId);
        abort_unless($group->isMember($r->user()), 403);

        if ($group->message_lock && !Gate::allows('send-group-message', $group)) {
            return response()->json([
                'message' => 'Only admins can send messages in this group.',
            ], 403);
        }

        $r->validate([
            'question'       => 'required|string|max:500',
            'options'        => 'required|array|min:2|max:10',
            'options.*'      => 'required|string|max:200',
            'allow_multiple' => 'boolean',
            'is_anonymous'   => 'boolean',
            'closes_at'      => 'nullable|date|after:now',
        ]);

        $message = DB::transaction(function () use ($r, $group) {
            $message = $group->messages()->create([
                'sender_id' => $r->user()->id,
                'body'      => $r->input('question'),
            ]);

            $poll = DB::table('message_polls')->insertGetId([
                'message_id'      => null,
                'group_message_id' => $message->id,
                'question'        => $r->input('question'),
                'allow_multiple'   => (bool) $r->input('allow_multiple', false),
                'is_anonymous'     => (bool) $r->input('is_anonymous', false),
                'closes_at'        => $r->input('closes_at'),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            foreach ($r->input('options') as $idx => $optionText) {
                DB::table('message_poll_options')->insert([
                    'poll_id'    => $poll,
                    'text'       => $optionText,
                    'sort_order' => $idx,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $message;
        });

        $message->load(['sender', 'attachments', 'replyTo', 'forwardedFrom', 'reactions.user']);
        broadcast(new GroupMessageSent($message))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => new MessageResource($message),
        ], 201);
    }

    /**
     * POST /api/v1/groups/{id}/live-location
     * Start sharing live location in a group (creates a group message with location_data).
     * Body: { location_data: { latitude, longitude, is_live, expires_at, duration_minutes } }
     */
    public function startLiveLocation(Request $r, $groupId)
    {
        $group = Group::findOrFail($groupId);
        abort_unless($group->isMember($r->user()), 403);

        if ($group->message_lock && !Gate::allows('send-group-message', $group)) {
            return ErrorResponse::forbidden('Only admins can send messages in this group.');
        }

        $r->validate([
            'location_data' => 'required|array',
            'location_data.latitude' => 'required|numeric|between:-90,90',
            'location_data.longitude' => 'required|numeric|between:-180,180',
            'location_data.is_live' => 'required|boolean',
            'location_data.expires_at' => 'nullable|string',
            'location_data.duration_minutes' => 'nullable|integer|min:1',
        ]);

        $locationData = $r->input('location_data');

        $message = $group->messages()->create([
            'sender_id'     => $r->user()->id,
            'body'          => '',
            'type'          => 'live_location',
            'location_data' => $locationData,
        ]);

        $message->load(['sender', 'attachments', 'reactions.user']);
        broadcast(new GroupMessageSent($message))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => new MessageResource($message),
            'id'      => $message->id,
        ], 201);
    }

    /**
     * PUT /api/v1/group-messages/{id}/live-location
     * Update live location coordinates or mark as stopped.
     * Body: { latitude?, longitude?, stopped? }
     */
    public function updateLiveLocation(Request $r, $messageId)
    {
        $message = GroupMessage::findOrFail($messageId);
        abort_unless($message->sender_id === $r->user()->id, 403);

        $r->validate([
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'stopped'   => 'nullable|boolean',
        ]);

        $locationData = is_array($message->location_data)
            ? $message->location_data
            : (json_decode($message->location_data ?? '{}', true) ?? []);

        if ($r->boolean('stopped')) {
            $locationData['is_live'] = false;
        } else {
            $locationData['latitude']  = (float) $r->input('latitude', $locationData['latitude'] ?? 0);
            $locationData['longitude'] = (float) $r->input('longitude', $locationData['longitude'] ?? 0);
        }

        $message->update(['location_data' => $locationData]);

        try {
            broadcast(new GroupMessageSent($message->fresh(['sender', 'attachments', 'reactions.user'])))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Failed to broadcast group live-location update: ' . $e->getMessage());
        }

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/v1/groups/{id}/messages/{messageId}/pin
     * Pin a message inside the group (one pinned message per group; pinning another unpins the previous).
     */
    public function pinMessage(Request $r, $groupId, $messageId)
    {
        $group = Group::findOrFail($groupId);
        abort_unless($group->isMember($r->user()), 403);

        $message = GroupMessage::where('group_id', $groupId)->findOrFail($messageId);
        $message->load(['sender', 'attachments', 'replyTo', 'forwardedFrom', 'reactions.user']);

        // One pinned message per group: remove any existing pin, then insert
        GroupPinnedMessage::where('group_id', $groupId)->delete();

        GroupPinnedMessage::create([
            'group_id'   => (int) $groupId,
            'message_id' => (int) $messageId,
            'pinned_by'  => $r->user()->id,
            'pinned_at'  => now(),
        ]);

        return response()->json([
            'success'         => true,
            'pinned_message'  => new MessageResource($message),
        ]);
    }

    /**
     * DELETE /api/v1/groups/{id}/messages/pin
     * Unpin the currently pinned message in the group.
     */
    public function unpinMessage(Request $r, $groupId)
    {
        $group = Group::findOrFail($groupId);
        abort_unless($group->isMember($r->user()), 403);

        GroupPinnedMessage::where('group_id', $groupId)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/v1/groups/{id}/pinned-message
     * Returns the currently pinned message for this group (if any).
     */
    public function getPinnedMessage(Request $r, $groupId)
    {
        $group = Group::findOrFail($groupId);
        abort_unless($group->isMember($r->user()), 403);

        $pinned = GroupPinnedMessage::where('group_id', $groupId)
            ->orderBy('pinned_at', 'desc')
            ->first();

        if (!$pinned) {
            return response()->json(['data' => null]);
        }

        $message = GroupMessage::with(['sender', 'attachments', 'replyTo', 'forwardedFrom', 'reactions.user'])
            ->where('group_id', $groupId)
            ->find($pinned->message_id);

        if (! $message || $message->deleted_for_user_id === $r->user()->id) {
            return response()->json(['data' => null]);
        }
        if ($message->deleted_for_everyone_at) {
            return response()->json(['data' => null]);
        }
        if ($message->expires_at && $message->expires_at->isPast()) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => new MessageResource($message),
        ]);
    }

    /**
     * Delete a group message (for me or for everyone).
     * DELETE /api/v1/group-messages/{id}?delete_for=me|everyone
     */
    public function destroy(Request $r, $messageId)
    {
        $messageId = (int) $messageId;
        if ($messageId < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid message id',
            ], 400);
        }

        $message = GroupMessage::findOrFail($messageId);
        $group = Group::findOrFail($message->group_id);
        abort_unless($group->isMember($r->user()), 403);

        $deleteFor = strtolower((string) $r->input('delete_for', 'me'));
        if (! in_array($deleteFor, ['me', 'everyone'], true)) {
            $deleteFor = 'me';
        }
        $userId = (int) $r->user()->id;

        if ($deleteFor === 'everyone') {
            if ($message->is_system) {
                return response()->json([
                    'success' => false,
                    'message' => 'System messages cannot be deleted for everyone',
                ], 422);
            }
            if ((int) $message->sender_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only delete your own messages',
                ], 403);
            }

            $dfFlag = \App\Models\FeatureFlag::where('key', 'delete_for_everyone')->first();
            if ($dfFlag !== null && ! $dfFlag->enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delete for everyone feature is not available',
                ], 403);
            }

            if ($message->created_at->lt(now()->subHour())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Messages can only be deleted for everyone within 1 hour of sending',
                ], 422);
            }

            if ($message->deleted_for_everyone_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message already deleted for everyone',
                ], 422);
            }

            $message->deleted_for_everyone_at = now();
            $message->save();

            broadcast(new GroupMessageDeleted($group->id, $message->id, $userId))->toOthers();

            return response()->json([
                'success' => true,
                'message' => 'Message deleted for everyone',
                'deleted_for_everyone' => true,
            ]);
        }

        $message->deleteForUser($userId);

        return response()->json([
            'success' => true,
            'message' => 'Message deleted successfully',
            'deleted_for_everyone' => false,
        ]);
    }
}
