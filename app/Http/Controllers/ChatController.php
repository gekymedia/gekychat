<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessageRead;
use App\Events\MessageReacted;
use App\Events\MessageSent;
use App\Events\MessageStatusUpdated;
use App\Events\UserTyping;

// cross-type forwarding to groups
use App\Events\GroupMessageSent;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageStatus;

// groups (kept as-is in your app)
use App\Models\Group;
use App\Models\GroupMessage;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    const MESSAGES_PER_PAGE = 50;

    /** Small helper so we don’t repeat membership checks everywhere. */
    protected function ensureMember(Conversation $conversation): void
    {
        abort_unless(
            $conversation->members()->whereKey(Auth::id())->exists(),
            403,
            'Not a participant of this conversation.'
        );
    }

    public function send(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'body'            => 'nullable|string',
            'reply_to'        => 'nullable|exists:messages,id',
            'forward_from'    => 'nullable|exists:messages,id',
            'attachments'     => 'nullable|array',
            'attachments.*'   => 'file|mimes:jpg,jpeg,png,gif,webp,pdf,zip,doc,docx,mp4,mp3,mov,wav|max:10240',
            'is_encrypted'    => 'nullable|boolean',
            'expires_in'      => 'nullable|integer|min:0|max:168',
        ]);

        $conversation = Conversation::findOrFail($request->conversation_id);
        $this->ensureMember($conversation);

        if (
            !$request->filled('body')
            && !$request->hasFile('attachments')
            && !$request->filled('forward_from')
        ) {
            throw ValidationException::withMessages([
                'body' => 'Type a message, attach a file, or forward a message.',
            ]);
        }

        $plainBody   = $request->input('body', '');
        $isEncrypted = (bool) $request->boolean('is_encrypted');
        $bodyToStore = $plainBody;

        if ($isEncrypted && $plainBody !== '') {
            $bodyToStore = Crypt::encryptString($plainBody);
        }

        $expiresAt = null;
        if ($request->filled('expires_in')) {
            $hours = (int) $request->input('expires_in');
            if ($hours > 0) {
                $expiresAt = now()->addHours($hours);
            }
        }

        $forwardChain = null;
        if ($request->filled('forward_from')) {
            $originalMessage = Message::with('sender')->find($request->forward_from);
            $forwardChain    = $originalMessage ? $originalMessage->buildForwardChain() : null;
        }

        $message = Message::create([
            'conversation_id'   => $conversation->id,
            'sender_id'         => Auth::id(),
            'body'              => $bodyToStore,
            'reply_to'          => $request->input('reply_to'),
            'forwarded_from_id' => $request->input('forward_from'),
            'forward_chain'     => $forwardChain,
            'is_encrypted'      => $isEncrypted,
            'expires_at'        => $expiresAt,
        ]);

        MessageStatus::create([
            'message_id' => $message->id,
            'user_id'    => Auth::id(),
            'status'     => MessageStatus::STATUS_SENT,
            'updated_at' => now(),
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments', 'public');
                $message->attachments()->create([
                    'user_id'       => Auth::id(),
                    'file_path'     => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type'     => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                ]);
            }
        }

        $message->load(['sender', 'attachments', 'replyTo', 'forwardedFrom', 'reactions.user']);

        broadcast(new MessageSent($message))->toOthers();
        broadcast(new MessageStatusUpdated($message->id, MessageStatus::STATUS_SENT))->toOthers();

        // simple bot reply for the special bot user if applicable
        if (!$isEncrypted && $plainBody !== '') {
            $this->handleBotReply($conversation->id, $plainBody);
        }

        // helpful for clients that don’t want to decrypt
        $message->setAttribute('body_plain', $plainBody);

        return response()->json([
            'status'  => 'success',
            'message' => $message,
        ]);
    }

    public function history($conversationId)
    {
        $conversation = Conversation::with([
            'members:id,name,phone,avatar_path',
        ])->findOrFail($conversationId);

        $this->ensureMember($conversation);

        $messages = Message::with([
                'sender',
                'attachments',
                'replyTo',
                'forwardedFrom',
                'statuses',
                'reactions.user',
            ])
            ->where('conversation_id', $conversationId)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->paginate(self::MESSAGES_PER_PAGE);

        $messages->getCollection()->transform(function ($message) {
            if ($message->is_encrypted) {
                try {
                    $message->body = Crypt::decryptString($message->body);
                } catch (\Throwable $e) {
                    $message->body = '[Encrypted message]';
                }
            }
            return $message;
        });

        // compute the "other user" for DM (null for groups)
        $otherUser = null;
        if (!$conversation->is_group) {
            $otherUser = $conversation->members
                ->firstWhere('id', '!=', Auth::id());
        }

        return response()->json([
            'messages'   => $messages,
            'other_user' => $otherUser,
        ]);
    }

    public function updateStatus(Request $request)
    {
        $request->validate([
            'message_id' => 'required|exists:messages,id',
            'status'     => 'required|in:sent,delivered,read',
        ]);

        $message      = Message::findOrFail($request->message_id);
        $conversation = $message->conversation()->with('members:id')->firstOrFail();
        $this->ensureMember($conversation);

        MessageStatus::updateOrCreate(
            ['message_id' => $message->id, 'user_id' => Auth::id()],
            ['status' => $request->status, 'updated_at' => now()]
        );

        if ($request->status === MessageStatus::STATUS_READ) {
            $message->read_at = now();
            $message->save();
        }

        broadcast(new MessageStatusUpdated($message->id, $request->status))->toOthers();

        return response()->json(['status' => 'success']);
    }

    public function addReaction(Request $request)
    {
        $request->validate([
            'message_id' => 'required|exists:messages,id',
            'reaction'   => 'required|string|max:2',
        ]);

        $message      = Message::findOrFail($request->message_id);
        $conversation = $message->conversation()->with('members:id')->firstOrFail();
        $this->ensureMember($conversation);

        $message->reactions()->updateOrCreate(
            ['user_id' => Auth::id()],
            ['reaction' => $request->reaction]
        );

        broadcast(new MessageReacted($message->id, Auth::id(), $request->reaction))->toOthers();

        return response()->json(['status' => 'success']);
    }

    /** Legacy: forward only to other DMs. (Kept for web actions) */
    public function forwardMessage(Request $request)
    {
        $request->validate([
            'message_id'         => 'required|exists:messages,id',
            'conversation_ids'   => 'required|array',
            'conversation_ids.*' => 'exists:conversations,id',
        ]);

        $originalMessage = Message::with(['sender', 'attachments', 'reactions.user'])
            ->findOrFail($request->message_id);

        $forwarded = [];

        foreach ($request->conversation_ids as $targetConversationId) {
            $conversation = Conversation::find($targetConversationId);

            if (!$conversation || !$conversation->members()->whereKey(Auth::id())->exists()) {
                continue;
            }

            $msg = Message::create([
                'conversation_id'   => $targetConversationId,
                'sender_id'         => Auth::id(),
                'body'              => $originalMessage->body ?? '',
                'forwarded_from_id' => $originalMessage->id,
                'forward_chain'     => $originalMessage->buildForwardChain(),
            ]);

            if ($originalMessage->attachments->isNotEmpty()) {
                foreach ($originalMessage->attachments as $attachment) {
                    $ext     = pathinfo($attachment->file_path, PATHINFO_EXTENSION);
                    $newPath = 'attachments/' . uniqid('fwd_', true) . ($ext ? ('.' . $ext) : '');
                    Storage::disk('public')->copy($attachment->file_path, $newPath);

                    $msg->attachments()->create([
                        'user_id'       => Auth::id(),
                        'file_path'     => $newPath,
                        'original_name' => $attachment->original_name,
                        'mime_type'     => $attachment->mime_type,
                        'size'          => $attachment->size,
                    ]);
                }
            }

            $msg->load(['sender', 'attachments', 'forwardedFrom', 'reactions.user']);
            broadcast(new MessageSent($msg))->toOthers();

            $forwarded[] = $msg;
        }

        return response()->json([
            'status'   => 'success',
            'messages' => $forwarded,
        ]);
    }

    /** Sidebar data for web (DMs + Groups). */
    public function index()
    {
        $userId = Auth::id();

        // DMs (non-group conversations the user participates in)
        $conversations = Conversation::with([
                'members:id,name,phone,avatar_path',
                'lastMessage',
            ])
            ->where('is_group', false)
            ->whereHas('members', fn ($q) => $q->whereKey($userId))
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->get();

        // Groups (kept as separate model in your app)
        $groups = Group::with([
                'members:id',
                'messages' => function ($q) {
                    $q->with('sender:id,name,phone,avatar_path')->latest()->limit(1);
                },
            ])
            ->whereHas('members', fn ($q) => $q->where('users.id', $userId))
            ->orderByDesc(
                GroupMessage::select('created_at')
                    ->whereColumn('group_messages.group_id', 'groups.id')
                    ->latest()
                    ->take(1)
            )
            ->get();

        return view('chat.index', compact('conversations', 'groups'));
    }

    public function show($id)
    {
        $conversation = Conversation::with([
                'members:id,name,phone,avatar_path',
                'messages' => function ($query) {
                    $query->with([
                        'attachments',
                        'sender',
                        'reactions.user',
                        'replyTo.sender',
                    ])->orderBy('created_at', 'asc');
                },
            ])->findOrFail($id);

        $this->ensureMember($conversation);

        // Mark unread as read for me (simple per-message read_at flag)
        $unreadIds = $conversation->messages
            ->where('sender_id', '!=', Auth::id())
            ->whereNull('read_at')
            ->pluck('id')
            ->all();

        if (!empty($unreadIds)) {
            Message::whereIn('id', $unreadIds)->update(['read_at' => now()]);
            broadcast(new MessageRead($conversation->id, Auth::id(), $unreadIds))->toOthers();
        }

        // Also prepare sidebar datasets again
        $userId = Auth::id();
        $conversations = Conversation::with(['members:id,name,phone,avatar_path', 'lastMessage'])
            ->where('is_group', false)
            ->whereHas('members', fn ($q) => $q->whereKey($userId))
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->get();

        $groups = Group::with([
                'members:id',
                'messages' => function ($q) {
                    $q->with('sender:id,name,phone,avatar_path')->latest()->limit(1);
                },
            ])
            ->whereHas('members', fn ($q) => $q->where('users.id', $userId))
            ->orderByDesc(
                GroupMessage::select('created_at')
                    ->whereColumn('group_messages.group_id', 'groups.id')
                    ->latest()
                    ->take(1)
            )
            ->get();

        return view('chat.index', compact('conversation', 'conversations', 'groups'));
    }

    public function new()
    {
        $users = \App\Models\User::where('id', '!=', Auth::id())
            ->orderByRaw('COALESCE(NULLIF(name,""), phone)')
            ->get(['id','name','phone','avatar_path']);

        return view('chat.new', compact('users'));
    }

    public function start(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id|different:' . Auth::id()
        ]);

        // Use your new helper on the Conversation model
        $conversation = Conversation::findOrCreateDirect(Auth::id(), (int) $request->user_id);

        return redirect()->route('chat.show', $conversation->id);
    }

    public function typing(Request $request)
    {
        $data = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'is_typing'       => 'sometimes|boolean',
            'typing'          => 'sometimes|boolean', // accept older payloads
        ]);

        $conversation = Conversation::findOrFail($data['conversation_id']);
        $this->ensureMember($conversation);

        $isTyping = array_key_exists('is_typing', $data)
            ? (bool) $data['is_typing']
            : (bool) ($data['typing'] ?? false);

        broadcast(new UserTyping(
            conversationId: $conversation->id,
            groupId: null,
            user: $request->user(),
            is_typing: $isTyping
        ))->toOthers();

        return response()->noContent();
    }

    public function markAsRead(Request $request)
    {
        $request->validate([
            'message_ids'     => 'required|array',
            'message_ids.*'   => 'exists:messages,id',
            'conversation_id' => 'required|exists:conversations,id',
        ]);

        $conversation = Conversation::findOrFail($request->conversation_id);
        $this->ensureMember($conversation);

        Message::whereIn('id', $request->message_ids)
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', Auth::id())
            ->update(['read_at' => now()]);

        broadcast(new MessageRead($conversation->id, Auth::id(), $request->message_ids))->toOthers();

        return response()->json(['status' => 'ok']);
    }

    public function clear($id)
    {
        $conversation = Conversation::with(['messages.attachments', 'messages.reactions'])
            ->findOrFail($id);

        $this->ensureMember($conversation);

        // Only delete my own sent messages and their attachments/reactions
        $myMessages = $conversation->messages->where('sender_id', Auth::id());

        foreach ($myMessages as $msg) {
            foreach ($msg->attachments as $att) {
                if ($att->file_path) {
                    Storage::disk('public')->delete($att->file_path);
                }
                $att->delete();
            }
            $msg->reactions()->delete();
            $msg->delete();
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleBotReply(int $conversationId, string $messageText): void
    {
        if ($messageText === '') return;

        $conversation = Conversation::with('members:id')->find($conversationId);
        if (!$conversation) return;

        // special "bot" user by phone
        $botId = \App\Models\User::where('phone', '0000000000')->value('id');
        if (!$botId) return;

        $isUserTalkingToBot = $conversation->members()->whereKey($botId)->exists();
        if (!$isUserTalkingToBot) return;

        $input    = mb_strtolower($messageText);
        $response = "I'm not sure I understand.";

        if (str_contains($input, 'hello') || str_contains($input, 'hi')) {
            $response = "Hello there! How can I help you today?";
        } elseif (str_contains($input, 'time')) {
            $response = "The current time is " . now()->format('h:i A');
        } elseif (str_contains($input, 'name')) {
            $response = "I'm GekyBot, your virtual assistant!";
        } elseif (str_contains($input, 'help')) {
            $response = "I can help with:\n- Time (ask 'what time is it?')\n- Date\n- Weather (coming soon)\nTry asking me something!";
        }

        $botMessage = Message::create([
            'conversation_id' => $conversationId,
            'sender_id'       => $botId,
            'body'            => $response,
        ]);

        MessageStatus::create([
            'message_id' => $botMessage->id,
            'user_id'    => $botId,
            'status'     => MessageStatus::STATUS_SENT,
            'updated_at' => now(),
        ]);

        $botMessage->load('sender');

        broadcast(new MessageSent($botMessage));
        broadcast(new MessageStatusUpdated($botMessage->id, MessageStatus::STATUS_SENT));
    }

    public function deleteMessage(Message $message)
    {
        // If you intend this to be "delete for me" only:
        $message->update(['deleted_for_user_id' => auth()->id()]);
        return response()->json(['success' => true]);
    }

    public function getMessages(Conversation $conversation)
    {
        $this->ensureMember($conversation);

        $messages = $conversation->messages()
            ->visibleTo(auth()->id())
            ->with(['sender', 'attachments'])
            ->latest()
            ->paginate(20);

        return response()->json($messages);
    }

    /**
     * NEW: Forward a 1:1 message to mixed targets (conversations and/or groups).
     * Request:
     *   message_id: int (messages.id)
     *   targets: [
     *     { "type": "conversation", "id": 123 },
     *     { "type": "group",        "id": 456 }
     *   ]
     */
    public function forwardToTargets(Request $request)
    {
        $data = $request->validate([
            'message_id'        => 'required|exists:messages,id',
            'targets'           => 'required|array|min:1',
            'targets.*.type'    => 'required|in:conversation,group',
            'targets.*.id'      => 'required|integer',
        ]);

        $original = Message::with(['sender', 'attachments'])->findOrFail($data['message_id']);

        // generic forward chain entry (for cross-type)
        $baseChain = $original->forward_chain ?? [];
        array_unshift($baseChain, [
            'id'           => $original->id,
            'sender'       => $original->sender->name ?? $original->sender->phone ?? 'User',
            'body'         => Str::limit($original->body ?? '', 100),
            'timestamp'    => optional($original->created_at)->toISOString(),
            'is_encrypted' => (bool)($original->is_encrypted ?? false),
            'source'       => 'dm',
        ]);

        $results = ['conversations' => [], 'groups' => []];

        foreach ($data['targets'] as $target) {
            if ($target['type'] === 'conversation') {
                $conversation = Conversation::find($target['id']);
                if (!$conversation || !$conversation->members()->whereKey(Auth::id())->exists()) {
                    continue;
                }

                $msg = Message::create([
                    'conversation_id'   => $conversation->id,
                    'sender_id'         => Auth::id(),
                    'body'              => $original->body ?? '',
                    'forwarded_from_id' => $original->id,                   // same-type forward link
                    'forward_chain'     => $original->buildForwardChain(),  // full chain
                ]);

                if ($original->attachments->isNotEmpty()) {
                    foreach ($original->attachments as $attachment) {
                        $ext     = pathinfo($attachment->file_path, PATHINFO_EXTENSION);
                        $newPath = 'attachments/' . uniqid('fwd_', true) . ($ext ? ('.' . $ext) : '');
                        Storage::disk('public')->copy($attachment->file_path, $newPath);

                        $msg->attachments()->create([
                            'user_id'       => Auth::id(),
                            'file_path'     => $newPath,
                            'original_name' => $attachment->original_name,
                            'mime_type'     => $attachment->mime_type,
                            'size'          => $attachment->size,
                        ]);
                    }
                }

                $msg->load(['sender', 'attachments', 'forwardedFrom', 'reactions.user']);
                broadcast(new MessageSent($msg))->toOthers();

                $results['conversations'][] = $msg;
            } else {
                // Forward to group (cross-type, no foreign key link)
                $group = Group::find($target['id']);
                if (!$group || !$group->members()->where('user_id', Auth::id())->exists()) {
                    continue;
                }

                $gm = $group->messages()->create([
                    'sender_id'         => Auth::id(),
                    'body'              => $original->body ?? '',
                    'forwarded_from_id' => null,       // cross-type: keep null
                    'forward_chain'     => $baseChain, // include DM source info
                    'delivered_at'      => now(),
                ]);

                if ($original->attachments->isNotEmpty()) {
                    foreach ($original->attachments as $attachment) {
                        $ext     = pathinfo($attachment->file_path, PATHINFO_EXTENSION);
                        $newPath = 'attachments/' . uniqid('fwd_', true) . ($ext ? ('.' . $ext) : '');
                        Storage::disk('public')->copy($attachment->file_path, $newPath);

                        $gm->attachments()->create([
                            'user_id'       => Auth::id(),
                            'file_path'     => $newPath,
                            'original_name' => $attachment->original_name,
                            'mime_type'     => $attachment->mime_type,
                            'size'          => $attachment->size,
                        ]);
                    }
                }

                $gm->load(['sender', 'attachments', 'reactions.user']);
                broadcast(new GroupMessageSent($gm))->toOthers();

                $results['groups'][] = $gm;
            }
        }

        return response()->json([
            'status'  => 'success',
            'results' => $results,
        ]);
    }

    // Example of a filtered/paged messages endpoint (kept commented in your file)
    // public function messages($id, Request $req) {
    //     $q = $req->query('q');
    //     $query = Message::where('conversation_id', $id)->with(['sender','reactions','attachments']);
    //     if ($q) {
    //         $query->where(function($w) use ($q){
    //             $w->where('body','like',"%{$q}%")
    //               ->orWhereHas('attachments', fn($a)=>$a->where('original_name','like',"%{$q}%"));
    //         });
    //     }
    //     return MessageResource::collection($query->latest()->paginate(50));
    // }
}
