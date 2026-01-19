<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessageRead;
use App\Events\MessageReacted;
use App\Events\MessageSent;
use App\Events\MessageStatusUpdated;
use App\Events\MessageEdited;
use App\Events\UserTyping;

// cross-type forwarding to groups
use App\Events\GroupMessageSent;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageStatus;

// groups (kept as-is in your app)
use App\Models\Group;
use App\Models\GroupMessage;

use App\Models\Contact;
use App\Models\User;
use App\Models\Status;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    const MESSAGES_PER_PAGE = 30; // Reduced for faster initial load

    /** Updated: Use pivot-based membership check */
    protected function ensureMember(Conversation $conversation): void
    {
        // First check if user is authenticated
        $userId = Auth::id();
        if ($userId === null) {
            abort(401, 'You must be logged in to access this conversation.');
        }

        abort_unless(
            $conversation->isParticipant($userId),
            403,
            'Not a participant of this conversation.'
        );
    }

    public function send(Request $request)
    {
        \Log::info('=== MESSAGE SEND PROCESS STARTED ===', [
            'user_id' => Auth::id(),
            'conversation_id' => $request->conversation_id,
            'has_body' => $request->filled('body'),
            'has_attachments' => $request->hasFile('attachments'),
            'has_forward' => $request->filled('forward_from'),
            'is_encrypted' => $request->boolean('is_encrypted'),
        ]);

        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'body'            => 'nullable|string',
            'reply_to'        => 'nullable|exists:messages,id',
            'forward_from'    => 'nullable|exists:messages,id',
            'attachments'     => 'nullable|array',
            'attachments.*'   => 'file|mimes:jpg,jpeg,png,gif,webp,pdf,zip,doc,docx,mp4,mp3,mov,wav,webm,ogg|max:10240',
            'is_encrypted'    => 'nullable|boolean',
            'expires_in'      => 'nullable|integer|min:0|max:168',
        ]);

        \Log::info('Validation passed', [
            'conversation_id' => $request->conversation_id,
            'body_length' => strlen($request->input('body', '')),
            'attachment_count' => $request->hasFile('attachments') ? count($request->file('attachments')) : 0,
        ]);

        try {
            $conversation = Conversation::findOrFail($request->conversation_id);
            \Log::info('Conversation found', [
                'conversation_id' => $conversation->id,
                'is_group' => $conversation->is_group,
                'member_count' => $conversation->members->count(),
            ]);

            $this->ensureMember($conversation);
            \Log::info('User verified as conversation member');

            if (
                !$request->filled('body')
                && !$request->hasFile('attachments')
                && !$request->filled('forward_from')
            ) {
                \Log::warning('Message validation failed - empty content', [
                    'has_body' => $request->filled('body'),
                    'has_attachments' => $request->hasFile('attachments'),
                    'has_forward' => $request->filled('forward_from'),
                ]);

                throw ValidationException::withMessages([
                    'body' => 'Type a message, attach a file, or forward a message.',
                ]);
            }

            $plainBody   = $request->input('body', '');
            $isEncrypted = (bool) $request->boolean('is_encrypted');
            $bodyToStore = $plainBody;

            \Log::info('Processing message body', [
                'original_length' => strlen($plainBody),
                'is_encrypted' => $isEncrypted,
                'is_empty' => empty($plainBody),
            ]);

            if ($isEncrypted && $plainBody !== '') {
                $bodyToStore = Crypt::encryptString($plainBody);
                \Log::info('Body encrypted successfully', [
                    'encrypted_length' => strlen($bodyToStore),
                ]);
            }

            $expiresAt = null;
            if ($request->filled('expires_in')) {
                $hours = (int) $request->input('expires_in');
                if ($hours > 0) {
                    $expiresAt = now()->addHours($hours);
                    \Log::info('Expiration set', [
                        'hours' => $hours,
                        'expires_at' => $expiresAt,
                    ]);
                }
            }

            $forwardChain = null;
            if ($request->filled('forward_from')) {
                $originalMessage = Message::with('sender')->find($request->forward_from);
                \Log::info('Forwarding message', [
                    'original_message_id' => $request->forward_from,
                    'original_sender_id' => $originalMessage?->sender_id,
                ]);

                $forwardChain = $originalMessage ? $originalMessage->buildForwardChain() : null;
            }

            \Log::info('Starting database transaction for message creation');
            DB::beginTransaction();

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

            \Log::info('Message created successfully', [
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'sender_id' => $message->sender_id,
                'has_reply' => !is_null($message->reply_to),
                'has_forward' => !is_null($message->forwarded_from_id),
            ]);

            MessageStatus::create([
                'message_id' => $message->id,
                'user_id'    => Auth::id(),
                'status'     => MessageStatus::STATUS_SENT,
                'updated_at' => now(),
            ]);

            \Log::info('Message status created', [
                'message_id' => $message->id,
                'status' => MessageStatus::STATUS_SENT,
            ]);

            if ($request->hasFile('attachments')) {
                \Log::info('Processing attachments', [
                    'count' => count($request->file('attachments')),
                ]);

                foreach ($request->file('attachments') as $index => $file) {
                    $path = $file->store('attachments', 'public');

                    $attachment = $message->attachments()->create([
                        'user_id'       => Auth::id(),
                        'file_path'     => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type'     => $file->getClientMimeType(),
                        'size'          => $file->getSize(),
                    ]);

                    \Log::info('Attachment saved', [
                        'attachment_id' => $attachment->id,
                        'filename' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                        'path' => $path,
                    ]);
                }
            }

            DB::commit();
            \Log::info('Database transaction committed successfully');

            // Load relationships for broadcasting
            $message->load(['sender', 'attachments', 'replyTo', 'forwardedFrom', 'reactions.user']);
            \Log::info('Message relationships loaded', [
                'has_sender' => !is_null($message->sender),
                'attachment_count' => $message->attachments->count(),
                'has_reply_to' => !is_null($message->replyTo),
                'has_forwarded_from' => !is_null($message->forwardedFrom),
            ]);

            \Log::info('Attempting to broadcast MessageSent event', [
                'message_id' => $message->id,
                'is_group' => false,
                'conversation_id' => $conversation->id,
            ]);

            // ✅ FIXED: Use updated MessageSent event (removed second parameter)
            broadcast(new MessageSent($message))->toOthers();
            \Log::info('MessageSent event broadcast successfully');

            \Log::info('Attempting to broadcast MessageStatusUpdated event', [
                'message_id' => $message->id,
                'status' => MessageStatus::STATUS_SENT,
            ]);

            // ✅ FIXED: Use updated MessageStatusUpdated event with conversation_id
            broadcast(new MessageStatusUpdated(
                $message->id,
                MessageStatus::STATUS_SENT,
                $conversation->id
            ))->toOthers();
            \Log::info('MessageStatusUpdated event broadcast successfully');

            // Handle bot reply if applicable (only for text messages, not voice-only)
            if (!$isEncrypted && !empty($plainBody) && is_string($plainBody)) {
                \Log::info('Checking for bot reply', [
                    'message_body' => $plainBody,
                ]);
                // Use BotService for AI-powered responses
                $botUserId = User::where('phone', '0000000000')->value('id');
                if ($botUserId && $conversation->isParticipant($botUserId) && Auth::id() !== $botUserId) {
                    try {
                        $botService = app(\App\Services\BotService::class);
                        $botService->handleDirectMessage($conversation->id, $plainBody, Auth::id());
                    } catch (\Exception $e) {
                        \Log::error('Failed to trigger bot response: ' . $e->getMessage());
                    }
                }
            }

            // Add plain body for client-side display (won't be saved to DB)
            $message->setAttribute('body_plain', $plainBody);

            // Generate HTML for immediate response
            $html = view('chat.shared.message', [
                'message' => $message,
                'isGroup' => false,
                'group' => null
            ])->render();

            \Log::info('=== MESSAGE SEND PROCESS COMPLETED SUCCESSFULLY ===', [
                'message_id' => $message->id,
                'response_has_html' => !empty($html),
                'html_length' => strlen($html),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => $message,
                'html' => $html
            ]);
        } catch (ValidationException $e) {
            \Log::warning('Validation exception in message send', [
                'errors' => $e->errors(),
                'user_id' => Auth::id(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Exception during message send process', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Rollback transaction if it was started
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
                \Log::info('Database transaction rolled back due to exception');
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to send message: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function history(Request $request, Conversation $conversation)
    {
        $this->ensureMember($conversation);

        $beforeId = $request->input('before_id') ?? $request->query('before_id');
        $limit = min((int)($request->input('limit') ?? $request->query('limit') ?? self::MESSAGES_PER_PAGE), 50);

        // ✅ FIXED: Use visibleTo scope to filter deleted messages
        $query = Message::with([
            'sender:id,name,phone,avatar_path',
            'attachments:id,attachable_id,attachable_type,file_path,mime_type,size,original_name',
            'replyTo.sender:id,name,phone,avatar_path',
            'statuses' => function($query) {
                $query->where('user_id', Auth::id());
            },
            'reactions.user:id,name,avatar_path',
        ])
            ->where('conversation_id', $conversation->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->visibleTo(auth()->id());

        // If before_id is provided, load messages older than that ID
        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        $messages = $query->orderBy('id', 'desc')
            ->take($limit)
            ->get()
            ->reverse() // Reverse to show oldest to newest
            ->values();

        // Check if there are more older messages
        $oldestMessageId = $messages->first()?->id ?? 0;
        $hasMore = Message::where('conversation_id', $conversation->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->visibleTo(auth()->id())
            ->where('id', '<', $oldestMessageId)
            ->exists();

        // Transform encrypted messages
        $messages->transform(function ($message) {
            if ($message->is_encrypted) {
                try {
                    $message->body = Crypt::decryptString($message->body);
                } catch (\Throwable $e) {
                    $message->body = '[Encrypted message]';
                }
            }
            return $message;
        });

        return response()->json([
            'data' => $messages,
            'has_more' => $hasMore,
            'oldest_message_id' => $oldestMessageId,
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


        // ✅ FIXED: Use updated MessageStatusUpdated event with conversation_id
        broadcast(new MessageStatusUpdated(
            $message->id,
            $request->status,
            $conversation->id
        ))->toOthers();

        return response()->json(['status' => 'success']);
    }

    public function addReaction(Request $request)
    {
        $request->validate([
            'message_id' => 'required|exists:messages,id',
            'emoji' => 'required|string|max:10'
        ]);

        try {
            $message = Message::findOrFail($request->message_id);

            // Use the ensureMember method for consistency
            $this->ensureMember($message->conversation);

            $reaction = $message->reactions()->updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'reaction' => $request->emoji
                ],
                [
                    'reaction' => $request->emoji
                ]
            );

            // ✅ FIXED: Use updated MessageReacted event with conversation_id
            broadcast(new MessageReacted(
                $message->id,
                auth()->id(),
                $request->emoji,
                $message->conversation_id
            ))->toOthers();

            return response()->json([
                'success' => true,
                'reaction' => $reaction,
                'message' => 'Reaction added successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Reaction error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add reaction'
            ], 500);
        }
    }

    public function editMessage(Request $request, $id)
    {
        $request->validate([
            'body' => 'required|string|max:1000'
        ]);

        try {
            $message = Message::findOrFail($id);

            // Check if user can edit this message
            if ($message->sender_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only edit your own messages'
                ], 403);
            }

            $plainBody = $request->input('body');
            $bodyToStore = $plainBody;

            // Handle encryption if the original message was encrypted
            if ($message->is_encrypted && $plainBody !== '') {
                $bodyToStore = Crypt::encryptString($plainBody);
            }

            // Update the message
            $message->update([
                'body' => $bodyToStore,
                'edited_at' => now()
            ]);

            // Reload relationships
            $message->load(['sender', 'attachments', 'replyTo', 'forwardedFrom', 'reactions.user']);

            // ✅ FIXED: Use MessageEdited event
            broadcast(new MessageEdited($message))->toOthers();

            return response()->json([
                'success' => true,
                'message' => $message,
                'body_plain' => $plainBody
            ]);
        } catch (\Exception $e) {
            \Log::error('Edit message error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to edit message'
            ], 500);
        }
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

            // Skip if conversation doesn't exist or user is not authenticated/not a participant
            if (!$conversation || !Auth::check() || !$conversation->isParticipant(Auth::id())) {
                continue;
            }

            $msg = Message::create([
                'conversation_id'   => $targetConversationId,
                'sender_id'         => Auth::id(),
                'body'              => $originalMessage->body ?? '',
                'forwarded_from_id' => $originalMessage->id,
                'forward_chain'     => $originalMessage->buildForwardChain(),
                'location_data'     => $originalMessage->location_data,
                'contact_data'      => $originalMessage->contact_data,
                'call_data'         => $originalMessage->call_data,
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

            // ✅ FIXED: Use MessageSent event
            broadcast(new MessageSent($msg))->toOthers();

            $forwarded[] = $msg;
        }

        return response()->json([
            'status'   => 'success',
            'messages' => $forwarded,
        ]);
    }

    // Add this method to your ChatController
    public function getUserProfile($userId)
    {
        try {
            $user = User::with(['contacts' => function ($query) {
                $query->where('user_id', auth()->id());
            }])->findOrFail($userId);

            $currentUser = auth()->user();
            $isContact = $currentUser->isContact($userId);
            $contact = $currentUser->getContact($userId);

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'initial' => $user->initial,
                    'is_online' => $user->is_online,
                    'last_seen_at' => $user->last_seen_at,
                    'created_at' => $user->created_at,
                    'is_contact' => $isContact,
                    'contact_data' => $contact ? [
                        'id' => $contact->id,
                        'display_name' => $contact->display_name,
                        'phone' => $contact->phone,
                        'note' => $contact->note,
                        'is_favorite' => $contact->is_favorite,
                        'created_at' => $contact->created_at
                    ] : null
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    }

    // Build forward-picker datasets for the Blade JSON block
    private function buildForwardDatasets(int $meId, $conversations, $groups): array
    {
        $forwardDMs = ($conversations ?? collect())->map(function ($c) use ($meId) {
            return [
                'id'       => $c->id,
                'slug'     => $c->slug,
                'title'    => $c->title,
                'subtitle' => $c->is_saved_messages ? 'Saved Messages' : ($c->other_user?->phone ?? null),
                'avatar'   => $c->avatar_url,
                'type'     => 'dm',
                'is_saved_messages' => $c->is_saved_messages,
            ];
        })->values();

        $forwardGroups = ($groups ?? collect())->map(function ($g) use ($meId) {
            // Get user's role in the group
            $member = $g->members()->where('user_id', $meId)->first();
            $userRole = $member?->pivot->role ?? 'member';
            $isAdmin = $userRole === 'admin' || $g->owner_id === $meId;
            
            return [
                'id'       => $g->id,
                'slug'     => $g->slug,
                'title'    => $g->name ?? 'Group',
                'subtitle' => $g->type === 'channel' ? 'Public Channel' : 'Private Group',
                'avatar'   => $g->avatar_url,
                'type'     => 'group',
                'is_public' => $g->is_public,
                'user_role' => $userRole,
                'is_admin' => $isAdmin,
            ];
        })->values();

        return [$forwardDMs, $forwardGroups];
    }

    /** Sidebar data for web (DMs + Groups). */
   public function index()
{
    // Ensure user is authenticated (middleware should handle this, but add safety check)
    if (!Auth::check()) {
        return redirect()->route('login');
    }

    $userId = Auth::id();
    $user = Auth::user();

    // Additional safety check
    if (!$user) {
        return redirect()->route('login');
    }

        $conversations = $user->conversations()
            ->with([
                'members',
                'lastMessage',
            ])
            ->withMax('messages', 'created_at')
            ->whereNull('conversation_user.archived_at') // Exclude archived conversations
            // Sort pinned chats first, then by most recent message
            ->orderByDesc('conversation_user.pinned_at')
            ->orderByDesc('messages_max_created_at')
            ->get()
            ->each(function ($conversation) use ($userId) {
                // Use the model's unreadCountFor method for consistency with getUnreadCountAttribute
                $conversation->unread_count = $conversation->unreadCountFor($userId);
            });

    $groups = $user->groups()
        ->with([
            'members',
            'messages' => function ($q) {
                $q->with('sender')->latest()->limit(1);
            },
        ])
        ->orderByDesc(
            GroupMessage::select('created_at')
                ->whereColumn('group_messages.group_id', 'groups.id')
                ->latest()
                ->take(1)
        )
        ->get()
        ->each(function ($group) use ($userId) {
            // Calculate unread count using the model's method for consistency
            $group->unread_count = $group->getUnreadCountForUser($userId);
        });

    [$forwardDMs, $forwardGroups] = $this->buildForwardDatasets($userId, $conversations, $groups);

    // Get active statuses for sidebar (including own status)
    $otherStatuses = Status::with(['user'])
        ->notExpired()
        ->visibleTo($userId)
        ->where('user_id', '!=', $userId)
        ->orderBy('created_at', 'desc')
        ->get()
        ->groupBy('user_id')
        ->map(function ($userStatuses) use ($userId) {
            $status = $userStatuses->first();
            // Check if any status from this user is unread
            $status->is_unread = $userStatuses->contains(function ($s) use ($userId) {
                return !$s->views()->where('user_id', $userId)->exists();
            });
            return $status;
        })
        ->values();

    // Get own status
    $ownStatus = Status::with(['user'])
        ->notExpired()
        ->where('user_id', $userId)
        ->orderBy('created_at', 'desc')
        ->first();

    // Merge own status with other statuses, placing own status first
    $statuses = collect();
    if ($ownStatus) {
        $ownStatus->is_unread = false; // Own status is always considered viewed
        $statuses->push($ownStatus);
    }
    $statuses = $statuses->merge($otherStatuses);

    return view('chat.index', compact('conversations', 'groups', 'forwardDMs', 'forwardGroups', 'statuses'));
}
    public function show(Request $request, Conversation $conversation)
    {
        $this->ensureMember($conversation);

        // Load conversation with members only (optimized eager loading)
        $conversation->load(['members:id,name,phone,avatar_path']);

        // Load only the latest messages (paginated) with efficient eager loading
        $messages = Message::with([
            'sender:id,name,phone,avatar_path', // Only load needed user fields
            'attachments:id,attachable_id,attachable_type,file_path,mime_type,size,original_name',
            'reactions.user:id,name,avatar_path',
            'replyTo.sender:id,name,phone,avatar_path',
            'statuses' => function($query) {
                $query->where('user_id', Auth::id());
            },
        ])
            ->where('conversation_id', $conversation->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->visibleTo(auth()->id())
            ->orderBy('created_at', 'desc') // Order by desc to get latest first
            ->take(self::MESSAGES_PER_PAGE) // Only load latest 50 messages
            ->get()
            ->reverse() // Reverse to show oldest to newest
            ->values();

        // Transform encrypted messages
        $messages->transform(function ($message) {
            if ($message->is_encrypted) {
                try {
                    $message->body = Crypt::decryptString($message->body);
                } catch (\Throwable $e) {
                    $message->body = '[Encrypted message]';
                }
            }
            return $message;
        });

        // Get the other user in the conversation (not the current user)
        $otherUser = $conversation->members->where('id', '!=', Auth::id())->first();

        // Use conversation title for saved messages, otherwise get other user's name
        if ($conversation->is_saved_messages) {
            $displayName = $conversation->title; // 'Saved Messages'
        } else {
            // Get contact display name if available
            $displayName = $otherUser->name ?? __('Unknown User');
            if ($otherUser) {
                $contact = Contact::where('user_id', Auth::id())
                    ->where('contact_user_id', $otherUser->id)
                    ->first();
                if ($contact && $contact->display_name) {
                    $displayName = $contact->display_name;
                }
            }
        }

        // Check if we have a reply private context from group
        $replyPrivateContext = session('reply_private_context');
        if ($replyPrivateContext) {
            // Clear the session after reading
            session()->forget('reply_private_context');
        }

        // Build header data with proper data types
        // For saved messages, use special icon and no user-specific data
        if ($conversation->is_saved_messages) {
            $headerData = [
                'name' => $displayName, // 'Saved Messages'
                'initial' => 'S', // Initial for Saved Messages
                'avatar' => null,
                'online' => false,
                'lastSeen' => null,
                'userId' => null, // No other user for saved messages
                'phone' => null,
                'created_at' => null,
            ];
        } else {
            $headerData = [
                'name' => $displayName,
                'initial' => strtoupper(substr($displayName, 0, 1)),
                'avatar' => $otherUser->avatar_url ?? null,
                'online' => (bool) ($otherUser->is_online ?? false),
                'lastSeen' => $otherUser->last_seen_at ?? null,
                'userId' => $otherUser->id ?? null,
                'phone' => $otherUser->phone ?? null,
                'created_at' => $otherUser->created_at ?? null,
            ];
        }

        // Efficiently mark unread messages as read (using query instead of loading all)
        $unreadMessages = Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', Auth::id())
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->visibleTo(auth()->id())
            ->whereDoesntHave('statuses', function ($query) {
                $query->where('user_id', Auth::id())
                    ->where('status', MessageStatus::STATUS_READ);
            })
            ->pluck('id');

        if ($unreadMessages->isNotEmpty()) {
            $unreadIds = $unreadMessages->all();
            $maxMessageId = max($unreadIds);

            // Batch insert/update message statuses
            $statuses = [];
            foreach ($unreadIds as $messageId) {
                $statuses[] = [
                    'message_id' => $messageId,
                    'user_id' => Auth::id(),
                    'status' => MessageStatus::STATUS_READ,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            // Use insertOrIgnore for better performance
            MessageStatus::insertOrIgnore($statuses);

            // Update pivot's last_read_message_id
            $conversation->members()->updateExistingPivot(Auth::id(), [
                'last_read_message_id' => $maxMessageId
            ]);

            // Broadcast MessageRead event
            broadcast(new MessageRead($conversation->id, Auth::id(), $unreadIds))->toOthers();
        } else {
            // Even if no unread messages, update last_read_message_id to latest message
            $latestMessageId = Message::where('conversation_id', $conversation->id)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->visibleTo(auth()->id())
                ->max('id');
            if ($latestMessageId) {
                $conversation->members()->updateExistingPivot(Auth::id(), [
                    'last_read_message_id' => $latestMessageId
                ]);
            }
        }

        // Assign messages to conversation for view compatibility
        $conversation->setRelation('messages', $messages);
        
        // Get prefilled text from query parameter (for /send/ links)
        $prefillText = $request->query('text');
        
        // Pass reply private context to view if it exists
        $replyPrivateContext = session('reply_private_context');
        if ($replyPrivateContext) {
            // Clear the session after reading
            session()->forget('reply_private_context');
        }
        
        // Check if there are more messages (for pagination indicator)
        $oldestMessageId = $messages->first()?->id ?? 0;
        $hasMoreMessages = Message::where('conversation_id', $conversation->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->visibleTo(auth()->id())
            ->where('id', '<', $oldestMessageId)
            ->exists();

        // sidebar datasets again
        $userId = Auth::id();
        $user = Auth::user();

        $conversations = $user->conversations()
            ->with(['members', 'lastMessage'])
            ->withMax('messages', 'created_at')
            ->whereNull('conversation_user.archived_at') // Exclude archived conversations
            ->orderByDesc('conversation_user.pinned_at')
            ->orderByDesc('messages_max_created_at')
            ->get()
            ->each(function ($conversation) use ($userId) {
                // Use the model's unreadCountFor method for consistency with getUnreadCountAttribute
                $conversation->unread_count = $conversation->unreadCountFor($userId);
            })
            ->values();

        $groups = $user->groups()
            ->with([
                'members',
                'messages' => function ($q) {
                    $q->with('sender')->latest()->limit(1);
                },
            ])
            ->orderByDesc(
                GroupMessage::select('created_at')
                    ->whereColumn('group_messages.group_id', 'groups.id')
                    ->latest()
                    ->take(1)
            )
            ->get()
            ->each(function ($group) use ($userId) {
                // Calculate unread count using the model's method for consistency
                $group->unread_count = $group->getUnreadCountForUser($userId);
            });

        [$forwardDMs, $forwardGroups] = $this->buildForwardDatasets($userId, $conversations, $groups);

        // Get active statuses for sidebar (including own status)
        $otherStatuses = Status::with(['user'])
            ->notExpired()
            ->visibleTo($userId)
            ->where('user_id', '!=', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('user_id')
            ->map(function ($userStatuses) use ($userId) {
                $status = $userStatuses->first();
                // Check if any status from this user is unread
                $status->is_unread = $userStatuses->contains(function ($s) use ($userId) {
                    return !$s->views()->where('user_id', $userId)->exists();
                });
                return $status;
            })
            ->values();

        // Get own status
        $ownStatus = Status::with(['user'])
            ->notExpired()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();

        // Merge own status with other statuses, placing own status first
        $statuses = collect();
        if ($ownStatus) {
            $ownStatus->is_unread = false; // Own status is always considered viewed
            $statuses->push($ownStatus);
        }
        $statuses = $statuses->merge($otherStatuses);

        return view('chat.index', compact(
            'conversation',
            'conversations',
            'groups',
            'replyPrivateContext',
            'forwardDMs',
            'forwardGroups',
            'headerData',
            'statuses',
            'prefillText',
            'hasMoreMessages'
        ));
    }


    public function new()
    {
        $users = User::where('id', '!=', Auth::id())
            ->orderByRaw('COALESCE(NULLIF(name,""), phone)')
            ->get(['id', 'name', 'phone', 'avatar_path']);

        return view('chat.new', compact('users'));
    }

    public function start(Request $request)
    {
        // Support both GET (query parameter) and POST (form data)
        $userId = $request->input('user_id') ?? $request->query('user_id');
        
        $request->validate([
            'user_id' => 'required|exists:users,id|different:' . Auth::id()
        ], [], [
            'user_id' => 'user'
        ]);

        $conversation = Conversation::findOrCreateDirect(Auth::id(), (int) $userId);

        return redirect()->route('chat.show', $conversation->slug);
    }

public function typing(Request $request)
{
    // Handle DELETE method (stop typing) - conversation_id can come from query params
    if ($request->isMethod('DELETE')) {
        $conversationId = $request->input('conversation_id') 
            ?? $request->query('conversation_id');
        
        if (!$conversationId) {
            return response()->json([
                'message' => 'conversation_id is required'
            ], 422);
        }
        
        $conversation = Conversation::findOrFail($conversationId);
        $this->ensureMember($conversation);
        $isTyping = false;
    } else {
        // Handle POST method - validate request body
        $data = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'is_typing'       => 'sometimes|boolean',
            'typing'          => 'sometimes|boolean',
        ]);

        $conversation = Conversation::findOrFail($data['conversation_id']);
        $this->ensureMember($conversation);
        
        $isTyping = array_key_exists('is_typing', $data)
            ? (bool) $data['is_typing']
            : (bool) ($data['typing'] ?? false);
    }

    // ✅ FIXED: Use UserTyping event (the one you actually have)
    broadcast(new UserTyping(
        $conversation->id, // conversationId
        null,              // groupId (null for direct messages)
        Auth::id(),        // userId
        $isTyping          // isTyping
    ))->toOthers();

    return response()->noContent();
}
    public function markAsRead(Request $request)
    {
        $request->validate([
            'message_ids'     => 'sometimes|array',
            'message_ids.*'   => 'exists:messages,id',
            'conversation_id' => 'required|exists:conversations,id',
        ]);

        $conversation = Conversation::findOrFail($request->conversation_id);
        $this->ensureMember($conversation);

        $userId = Auth::id();

        // If message_ids provided, mark those specific messages
        // Otherwise, mark all unread messages in the conversation
        if ($request->has('message_ids') && !empty($request->message_ids)) {
            $messageIds = $request->message_ids;
        } else {
            // Get all unread messages for this user in this conversation
            $messageIds = $conversation->messages()
                ->where('sender_id', '!=', $userId)
                ->whereDoesntHave('statuses', function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->where('status', MessageStatus::STATUS_READ);
                })
                ->pluck('id')
                ->toArray();
        }

        if (!empty($messageIds)) {
            // Get the highest message ID to mark as last read
            $maxMessageId = Message::whereIn('id', $messageIds)
                ->where('conversation_id', $conversation->id)
                ->max('id');

            if ($maxMessageId) {
                // Update the pivot table
                $conversation->members()->updateExistingPivot($userId, [
                    'last_read_message_id' => $maxMessageId
                ]);
            }

            // Update message_statuses for each message
            // Use firstOrCreate to handle race conditions better
            foreach ($messageIds as $messageId) {
                try {
                    MessageStatus::firstOrCreate(
                        [
                            'message_id' => $messageId,
                            'user_id' => $userId
                        ],
                        [
                            'status' => MessageStatus::STATUS_READ,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]
                    )->update([
                        'status' => MessageStatus::STATUS_READ,
                        'updated_at' => now()
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Handle duplicate entry error (race condition)
                    if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry')) {
                        // Try to update existing record
                        MessageStatus::where('message_id', $messageId)
                            ->where('user_id', $userId)
                            ->update([
                                'status' => MessageStatus::STATUS_READ,
                                'updated_at' => now()
                            ]);
                    } else {
                        throw $e;
                    }
                }
            }

            // ✅ FIXED: Use MessageRead event
            broadcast(new MessageRead($conversation->id, $userId, $messageIds))->toOthers();
        } else {
            // No unread messages, but still update last_read_message_id to latest
            $latestMessageId = $conversation->messages()->max('id');
            if ($latestMessageId) {
                $conversation->members()->updateExistingPivot($userId, [
                    'last_read_message_id' => $latestMessageId
                ]);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Mark a conversation as unread by resetting the last_read_message_id.
     */
    public function markAsUnread(Request $request, Conversation $conversation)
    {
        $this->ensureMember($conversation);
        
        $userId = Auth::id();
        
        // Reset last_read_message_id to null, which will make all messages appear as unread
        $conversation->members()->updateExistingPivot($userId, [
            'last_read_message_id' => null
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Conversation marked as unread'
        ]);
    }

    public function clear(Conversation $conversation)
    {
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

        $conversation = Conversation::with('members')->find($conversationId);
        if (!$conversation) return;

        // special "bot" user by phone
        $botId = User::where('phone', '0000000000')->value('id');
        if (!$botId) return;

        $isUserTalkingToBot = $conversation->isParticipant($botId);
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

        // Add a small delay before broadcasting bot message to ensure user's message appears first
        // This prevents the bot reply from appearing before the user's message in the UI
        usleep(500000); // 500 milliseconds
        
        // ✅ FIXED: Broadcast to all users (not just toOthers) so user receives bot message in real-time
        broadcast(new MessageSent($botMessage));

        // ✅ FIXED: Use MessageStatusUpdated event with conversation_id
        broadcast(new MessageStatusUpdated(
            $botMessage->id,
            MessageStatus::STATUS_SENT,
            $conversationId
        ));
    }

    public function deleteMessage($messageId)
    {
        try {
            $message = Message::find($messageId);

            if (!$message) {
                \Log::warning('Delete failed - message not found', [
                    'message_id' => $messageId,
                    'current_user' => auth()->id()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Message not found'
                ], 404);
            }

            \Log::info('Delete attempt', [
                'message_id' => $message->id,
                'message_sender_id' => $message->sender_id,
                'current_user_id' => auth()->id(),
                'is_own' => $message->sender_id === auth()->id()
            ]);

            // Check if user can delete this message
            if ($message->sender_id !== auth()->id()) {
                \Log::warning('Delete forbidden - not message owner', [
                    'message_sender' => $message->sender_id,
                    'current_user' => auth()->id()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'You can only delete your own messages'
                ], 403);
            }

            // ✅ FIXED: Proper soft delete using MessageStatus
            $message->statuses()->updateOrCreate(
                ['user_id' => auth()->id()],
                ['deleted_at' => now()]
            );

            // ✅ FIXED: Use MessageDeleted event
            broadcast(new MessageDeleted(
                messageId: $message->id,
                deletedBy: auth()->id(),
                conversationId: $message->conversation_id,
                groupId: null
            ))->toOthers();

            return response()->json([
                'success' => true,
                'message' => 'Message deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Delete message error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete message'
            ], 500);
        }
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
                // Skip if conversation doesn't exist or user is not authenticated/not a participant
                if (!$conversation || !Auth::check() || !$conversation->isParticipant(Auth::id())) {
                    continue;
                }

                $msg = Message::create([
                    'conversation_id'   => $conversation->id,
                    'sender_id'         => Auth::id(),
                    'body'              => $original->body ?? '',
                    'forwarded_from_id' => $original->id,
                    'forward_chain'     => $original->buildForwardChain(),
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

                // ✅ FIXED: Use MessageSent event
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
                    'forwarded_from_id' => null,
                    'forward_chain'     => $baseChain,
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

                // ✅ FIXED: Use GroupMessageSent event
                broadcast(new GroupMessageSent($gm))->toOthers();

                $results['groups'][] = $gm;
            }
        }

        return response()->json([
            'status'  => 'success',
            'results' => $results,
        ]);
    }

    public function startChatWithPhone(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:10'
        ]);

        $currentUser = Auth::user();
        $phone = $request->phone;

        // Normalize phone number
        $normalizedPhone = Contact::normalizePhone($phone);

        // Find or create user by phone
        $targetUser = User::firstOrCreate(
            ['phone' => $normalizedPhone],
            [
                'name' => $normalizedPhone,
                'password' => bcrypt(Str::random(16)),
                'phone_verified_at' => null,
            ]
        );

        // Create conversation
        $conversation = Conversation::findOrCreateDirect($currentUser->id, $targetUser->id);

        return response()->json([
            'success' => true,
            'conversation' => $conversation->load(['members', 'latestMessage']),
            'redirect_url' => route('chat.show', $conversation->slug)
        ]);
    }

    // Add this method to your ChatController
    public function processMessageContent($content)
    {
        // Process URLs
        $content = preg_replace(
            '/(https?:\/\/[^\s]+)/',
            '<a href="$1" target="_blank" class="linkify" rel="noopener noreferrer">$1</a>',
            $content
        );

        // Process emails
        $content = preg_replace(
            '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/',
            '<a href="mailto:$1" class="email-link">$1</a>',
            $content
        );

        // Process phone numbers (Ghana format)
        $content = preg_replace_callback(
            '/(?:\+?233|0)?([1-9]\d{8})/',
            function ($matches) {
                $fullMatch = $matches[0];
                $cleanNumber = $matches[1];

                // Normalize phone number
                if (str_starts_with($fullMatch, '0')) {
                    $normalized = '+233' . substr($fullMatch, 1);
                } elseif (str_starts_with($fullMatch, '233')) {
                    $normalized = '+' . $fullMatch;
                } else {
                    $normalized = '+233' . $fullMatch;
                }

                return '<a href="#" class="phone-link" data-phone="' . e($normalized) .
                    '" onclick="handlePhoneClick(\'' . e($normalized) . '\'); return false;">' .
                    e($fullMatch) . '</a>';
            },
            $content
        );

        return $content;
    }

    // Add this function to your ChatController or a helper file
    private function applyMarkdownFormatting($content)
    {
        // Bold: **text** or __text__
        $content = preg_replace('/(\*\*|__)(.*?)\1/', '<strong>$2</strong>', $content);

        // Italic: *text* or _text_
        $content = preg_replace('/(\*|_)(.*?)\1/', '<em>$2</em>', $content);

        // Strikethrough: ~text~
        $content = preg_replace('/~(.*?)~/', '<del>$1</del>', $content);

        // Monospace: ```text```
        $content = preg_replace('/```(.*?)```/', '<code>$1</code>', $content);

        return $content;
    }

    // Add these methods to your existing ChatController

    /**
     * Share location in direct chat
     */
    public function shareLocation(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
            'place_name' => 'nullable|string|max:255',
        ]);

        $conversation = Conversation::findOrFail($request->conversation_id);
        $this->ensureMember($conversation);

        $locationData = [
            'type' => 'location',
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'address' => $request->address,
            'place_name' => $request->place_name,
            'shared_at' => now()->toISOString(),
        ];

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => auth()->id(),
            'body' => '📍 Shared location',
            'location_data' => $locationData,
            'is_encrypted' => false, // Location sharing can't be encrypted
        ]);

        $message->load(['sender', 'attachments', 'reactions.user']);

        // ✅ FIXED: Use MessageSent event
        broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'status' => 'success',
            'message' => $message,
        ]);
    }

    /**
     * Share contact in direct chat
     */
    public function shareContact(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'contact_id' => 'required|exists:contacts,id',
        ]);

        $conversation = Conversation::findOrFail($request->conversation_id);
        $this->ensureMember($conversation);

        $contact = Contact::where('id', $request->contact_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $contactUser = User::where('phone', $contact->phone)->first();

        $contactData = [
            'type' => 'contact',
            'contact_id' => $contact->id,
            'display_name' => $contact->display_name ?? $contact->phone,
            'phone' => $contact->phone,
            'email' => $contact->email,
            'user_id' => $contactUser?->id,
            'shared_at' => now()->toISOString(),
        ];

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => auth()->id(),
            'body' => '👤 Shared contact',
            'contact_data' => $contactData,
            'is_encrypted' => false, // Contact sharing can't be encrypted
        ]);

        $message->load(['sender', 'attachments', 'reactions.user']);

        // ✅ FIXED: Use MessageSent event
        broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'status' => 'success',
            'message' => $message,
        ]);
    }

    /**
     * Toggle the pinned status of a conversation for the authenticated user.
     * If the conversation is already pinned, it will be unpinned; otherwise
     * it will be pinned. To prevent a large number of pinned chats from
     * cluttering the sidebar, a maximum of five pinned conversations is
     * enforced. When pinning a new conversation beyond the limit, the
     * oldest pinned chat is automatically unpinned.
     */
    public function pin(Request $request, Conversation $conversation)
    {
        // Ensure the user is part of this conversation
        $this->ensureMember($conversation);

        $user = Auth::user();
        // Retrieve pivot record
        $pivot = $conversation->members()->where('users.id', $user->id)->first()->pivot;
        $isPinned = !is_null($pivot->pinned_at);

        if ($isPinned) {
            // Unpin by setting pinned_at to null
            $conversation->members()->updateExistingPivot($user->id, ['pinned_at' => null]);
            return response()->json(['pinned' => false]);
        }

        // Pinning: ensure not exceeding 5 pinned conversations
        $pinnedConversations = $user->conversations()->wherePivotNotNull('pinned_at')
            ->orderBy('conversation_user.pinned_at')
            ->get();
        if ($pinnedConversations->count() >= 5) {
            // Unpin the oldest pinned conversation
            $oldest = $pinnedConversations->first();
            $oldest->members()->updateExistingPivot($user->id, ['pinned_at' => null]);
        }
        // Pin the requested conversation
        $conversation->members()->updateExistingPivot($user->id, ['pinned_at' => now()]);
        return response()->json(['pinned' => true]);
    }
}
