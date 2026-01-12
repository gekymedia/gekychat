<?php
namespace App\Http\Controllers\Api\V1;

use App\Events\GroupMessageReadEvent;
use App\Events\GroupMessageSent;
use App\Events\TypingInGroup;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Attachment;
use App\Models\Group;
use App\Models\GroupMessage;
use Illuminate\Http\Request;

class GroupMessageController extends Controller
{
    /**
     * Get messages in a group with pagination
     * GET /api/v1/groups/{id}/messages
     */
    public function index(Request $r, $groupId)
    {
        $r->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'before' => 'nullable|date',
            'after' => 'nullable|date',
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

        if ($r->filled('before')) {
            $query->where('created_at', '<', $r->before);
        }

        if ($r->filled('after')) {
            $query->where('created_at', '>', $r->after);
        }

        $messages = $query->limit($limit)->get()->sortBy('created_at')->values();

        return response()->json([
            'data' => MessageResource::collection($messages),
        ]);
    }

    public function store(Request $r, $groupId)
    {
        $r->validate([
            'body' => 'nullable|string|max:5000',
            'reply_to' => 'nullable|integer|exists:group_messages,id',
            'forward_from_id' => 'nullable|integer|exists:group_messages,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'integer|exists:attachments,id',
        ]);

        $g = Group::findOrFail($groupId);
        abort_unless($g->isMember($r->user()), 403);

        if (!$r->filled('body') && !$r->filled('attachments') && !$r->filled('forward_from_id')) {
            return response()->json(['message'=>'Please enter a message, attach a file, or forward a message.'], 422);
        }

        $fwdChain = null;
        if ($r->filled('forward_from_id')) {
            $orig = GroupMessage::with('sender')->find($r->forward_from_id);
            $fwdChain = $orig ? $orig->buildForwardChain() : null;
        }

        $m = $g->messages()->create([
            'sender_id' => $r->user()->id,
            'body' => (string)($r->body ?? ''),
            'reply_to' => $r->reply_to,
            'forwarded_from_id' => $r->forward_from_id,
            'forward_chain' => $fwdChain,
            'delivered_at' => now(),
        ]);

        if ($r->filled('attachments')) {
            Attachment::whereIn('id',$r->attachments)->update(['attachable_id'=>$m->id,'attachable_type'=>GroupMessage::class]);
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
        broadcast(new GroupMessageReadEvent($m->group_id, $m->id, $r->user()->id))->toOthers();
        return response()->json(['ok'=>true]);
    }

    public function typing(Request $r, $groupId)
    {
        $r->validate(['is_typing'=>'required|boolean']);
        $g = Group::findOrFail($groupId);
        abort_unless($g->isMember($r->user()), 403);
        broadcast(new TypingInGroup((int)$groupId, $r->user()->id, (bool)$r->is_typing))->toOthers();
        return response()->json(['ok'=>true]);
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
            return response()->json(['message' => 'Original sender not found.'], 404);
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
}
