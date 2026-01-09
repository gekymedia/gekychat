<?php
namespace App\Http\Controllers\Api\V1;

use App\Events\MessageSent;
use App\Events\MessageRead;
use App\Events\TypingInGroup;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function store(Request $r, $conversationId)
    {
        $r->validate([
            'client_id' => 'nullable|string|max:100',
            'body' => 'nullable|string',
            'reply_to' => 'nullable|exists:messages,id',
            'forward_from' => 'nullable|exists:messages,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'integer|exists:attachments,id',
            'is_encrypted' => 'nullable|boolean',
            'expires_in' => 'nullable|integer|min:0|max:168',
        ]);

        $conv = Conversation::findOrFail($conversationId);
        $userId = $r->user()->id;
        
        // Debug: Log the check
        $isParticipant = $conv->isParticipant($userId);
        if (!$isParticipant) {
            Log::warning("User {$userId} tried to send message to conversation {$conversationId} but is not a participant");
            // Check if conversation exists in pivot table
            $pivotCheck = DB::table('conversation_user')
                ->where('conversation_id', $conversationId)
                ->where('user_id', $userId)
                ->exists();
            Log::info("Pivot table check result: " . ($pivotCheck ? 'exists' : 'not found'));
        }
        
        abort_unless($isParticipant, 403, 'You are not a participant in this conversation.');

        // IDEMPOTENCY: Check if message with client_id already exists
        if ($r->filled('client_id')) {
            $existing = Message::where('client_uuid', $r->client_id)
                ->where('conversation_id', $conv->id)
                ->where('sender_id', $r->user()->id)
                ->first();

            if ($existing) {
                $existing->load(['sender','attachments','replyTo','forwardedFrom','reactions.user']);
                return response()->json(['data' => new MessageResource($existing)], 200); // Return existing
            }
        }

        // Check for file uploads (attachments[] in form data) or attachment IDs
        // Laravel receives 'attachments[]' as 'attachments' array when sent as multipart form data
        $hasFileUploads = false;
        $uploadedFiles = [];
        
        // Check for files - try both 'attachments' and all files in request
        if ($r->hasFile('attachments')) {
            $files = $r->file('attachments');
            if (is_array($files)) {
                $uploadedFiles = array_filter($files, fn($f) => $f && $f->isValid());
            } elseif ($files && $files->isValid()) {
                $uploadedFiles = [$files];
            }
            $hasFileUploads = count($uploadedFiles) > 0;
        } else {
            // Check all files in request (for attachments[] array uploads)
            $allFiles = $r->allFiles();
            foreach ($allFiles as $key => $file) {
                if (str_contains($key, 'attachment') || (is_array($file) && count($file) > 0)) {
                    if (is_array($file)) {
                        $uploadedFiles = array_merge($uploadedFiles, array_filter($file, fn($f) => $f && $f->isValid()));
                    } elseif ($file && $file->isValid()) {
                        $uploadedFiles[] = $file;
                    }
                }
            }
            $hasFileUploads = count($uploadedFiles) > 0;
        }
        
        $hasAttachmentIds = $r->filled('attachments') && is_array($r->attachments) && count($r->attachments) > 0;
        
        if (!$r->filled('body') && !$hasFileUploads && !$hasAttachmentIds && !$r->filled('forward_from')) {
            return response()->json(['message'=>'Type a message, attach a file, or forward a message.'], 422);
        }

        $expiresAt = $r->filled('expires_in') && (int)$r->expires_in>0 ? now()->addHours((int)$r->expires_in) : null;

        $forwardChain = null;
        if ($r->filled('forward_from')) {
            $orig = Message::with('sender')->find($r->forward_from);
            $forwardChain = $orig ? $orig->buildForwardChain() : null;
        }

        $msg = Message::create([
            'client_uuid' => $r->client_id ?? \Illuminate\Support\Str::uuid(),
            'conversation_id' => $conv->id,
            'sender_id' => $r->user()->id,
            'body' => (string)($r->body ?? ''),
            'reply_to' => $r->reply_to,
            'forwarded_from_id' => $r->forward_from,
            'forward_chain' => $forwardChain,
            'is_encrypted' => (bool)($r->is_encrypted ?? false),
            'expires_at' => $expiresAt,
        ]);

        // Handle file uploads (attachments[] as files)
        if ($hasFileUploads && !empty($uploadedFiles)) {
            foreach ($uploadedFiles as $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('attachments', 'public');
                    $attachment = Attachment::create([
                        'user_id' => $r->user()->id,
                        'attachable_id' => $msg->id,
                        'attachable_type' => Message::class,
                        'file_path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }
            }
        }
        
        // Handle attachment IDs (pre-uploaded attachments)
        if ($hasAttachmentIds) {
            Attachment::whereIn('id', $r->attachments)->update(['attachable_id'=>$msg->id, 'attachable_type'=>Message::class]);
        }

        $msg->load(['sender','attachments','replyTo','forwardedFrom','reactions.user']);
        
        // Mark as delivered for recipients
        $recipients = $conv->members()->where('users.id', '!=', $r->user()->id)->get();
        foreach ($recipients as $recipient) {
            $msg->markAsDeliveredFor($recipient->id);
        }

        broadcast(new MessageSent($msg))->toOthers();

        // Check if this is a message to the bot and trigger bot response
        $botUserId = \App\Models\User::where('phone', '0000000000')->value('id');
        if ($botUserId && $conv->isParticipant($botUserId) && $r->user()->id !== $botUserId && !empty($msg->body)) {
            // Dispatch bot response asynchronously
            try {
                $botService = app(\App\Services\BotService::class);
                $botService->handleDirectMessage($conv->id, $msg->body, $r->user()->id);
            } catch (\Exception $e) {
                \Log::error('Failed to trigger bot response: ' . $e->getMessage());
            }
        }

        return response()->json(['data' => new MessageResource($msg)], 201);
    }

    /**
     * Mark multiple messages as read
     * POST /api/v1/conversations/{id}/read
     */
    public function markConversationRead(Request $r, $conversationId)
    {
        $r->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'integer|exists:messages,id',
        ]);

        $conv = Conversation::findOrFail($conversationId);
        abort_unless($conv->isParticipant($r->user()->id), 403);

        $messages = Message::whereIn('id', $r->message_ids)
            ->where('conversation_id', $conversationId)
            ->get();

        $markedCount = 0;
        foreach ($messages as $msg) {
            $msg->markAsReadFor($r->user()->id);
            $markedCount++;
        }

        broadcast(new MessageRead($conversationId, $r->user()->id, $r->message_ids))->toOthers();

        return response()->json([
            'success' => true,
            'marked_count' => $markedCount,
        ]);
    }

    /**
     * Mark single message as read (for backward compatibility)
     * POST /api/v1/messages/{id}/read
     */
    public function markRead(Request $r, $messageId)
    {
        $msg = Message::findOrFail($messageId);
        abort_unless($msg->conversation->isParticipant($r->user()->id), 403);
        $msg->markAsReadFor($r->user()->id);
        broadcast(new MessageRead($msg->conversation_id, $r->user()->id, [$msg->id]))->toOthers();
        return response()->json(['success'=>true]);
    }

    public function typing(Request $r, $conversationId)
    {
        $r->validate(['is_typing'=>'required|boolean']);
        $conv = Conversation::findOrFail($conversationId);
        abort_unless($conv->isParticipant($r->user()->id), 403);
        broadcast(new TypingInGroup((int)$conversationId, $r->user()->id, (bool)$r->is_typing))->toOthers();
        return response()->json(['ok'=>true]);
    }

    /**
     * Get messages in a conversation with pagination
     * GET /api/v1/conversations/{id}/messages
     */
    public function index(Request $r, $conversationId)
    {
        $r->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'before' => 'nullable|integer|exists:messages,id',
            'after' => 'nullable|integer|exists:messages,id',
        ]);

        $conv = Conversation::findOrFail($conversationId);
        abort_unless($conv->isParticipant($r->user()->id), 403);

        $limit = $r->input('limit', 50);
        
        $query = Message::where('conversation_id', $conversationId)
            ->with(['sender', 'attachments', 'replyTo', 'reactions.user'])
            ->orderBy('id', 'desc');

        if ($r->filled('before')) {
            $query->where('id', '<', $r->before);
        }

        if ($r->filled('after')) {
            $query->where('id', '>', $r->after);
        }

        $messages = $query->limit($limit)->get()->reverse()->values();
        
        // Mark messages as delivered when fetched
        foreach ($messages as $msg) {
            if ($msg->sender_id !== $r->user()->id) {
                $msg->markAsDeliveredFor($r->user()->id);
            }
        }

        // Return in consistent format with MessageResource
        return response()->json([
            'data' => MessageResource::collection($messages),
            'has_more' => $query->count() > $limit,
        ]);
    }

    /**
     * Forward message to multiple conversations
     * POST /api/v1/messages/{id}/forward
     */
    public function forward(Request $r, $messageId)
    {
        $r->validate([
            'conversation_ids' => 'required|array|min:1',
            'conversation_ids.*' => 'integer|exists:conversations,id',
        ]);

        $msg = Message::with(['sender','attachments'])->findOrFail($messageId);
        abort_unless($msg->conversation->isParticipant($r->user()->id), 403);

        $newMessageIds = [];

        foreach ($r->conversation_ids as $targetConvId) {
            $targetConv = Conversation::findOrFail($targetConvId);
            
            // Check if user can access target conversation
            if (!$targetConv->isParticipant($r->user()->id)) {
                continue;
            }

            // Create forwarded message
            $forwardedMsg = Message::create([
                'client_uuid' => \Illuminate\Support\Str::uuid(),
                'conversation_id' => $targetConvId,
                'sender_id' => $r->user()->id,
                'body' => $msg->body,
                'forwarded_from_id' => $msg->id,
                'forward_chain' => $msg->buildForwardChain(),
            ]);

            // Copy attachments (reference, not duplicate files)
            if ($msg->attachments->isNotEmpty()) {
                foreach ($msg->attachments as $attachment) {
                    Attachment::create([
                        'attachable_type' => Message::class,
                        'attachable_id' => $forwardedMsg->id,
                        'original_name' => $attachment->original_name,
                        'file_path' => $attachment->file_path,
                        'mime_type' => $attachment->mime_type,
                        'size' => $attachment->size,
                    ]);
                }
            }

            $forwardedMsg->load(['sender','attachments','replyTo','forwardedFrom','reactions.user']);
            broadcast(new MessageSent($forwardedMsg))->toOthers();

            $newMessageIds[] = $forwardedMsg->id;
        }

        return response()->json([
            'success' => true,
            'forwarded_to' => count($newMessageIds),
            'new_message_ids' => $newMessageIds,
        ]);
    }

    /**
     * Update (edit) a message
     * PUT /api/v1/messages/{id}
     */
    public function update(Request $r, $messageId)
    {
        $r->validate([
            'body' => 'required|string|max:1000'
        ]);

        try {
            $message = Message::findOrFail($messageId);

            // Check if user can edit this message
            if ($message->sender_id !== $r->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only edit your own messages'
                ], 403);
            }

            $plainBody = $r->input('body');
            $bodyToStore = $plainBody;

            // Handle encryption if the original message was encrypted
            if ($message->is_encrypted && $plainBody !== '') {
                $bodyToStore = \Illuminate\Support\Facades\Crypt::encryptString($plainBody);
            }

            // Update the message
            $message->update([
                'body' => $bodyToStore,
                'edited_at' => now()
            ]);

            // Reload relationships
            $message->load(['sender', 'attachments', 'replyTo', 'forwardedFrom', 'reactions.user']);

            // Broadcast edit event
            broadcast(new \App\Events\MessageEdited($message))->toOthers();

            return response()->json([
                'success' => true,
                'data' => new MessageResource($message),
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

    /**
     * Delete a message (soft delete per user OR delete for everyone)
     * DELETE /api/v1/messages/{id}
     * 
     * PHASE 1: Supports both "delete for me" and "delete for everyone"
     * 
     * Query parameters:
     * - delete_for: 'me' (default) or 'everyone'
     * - Time limit: 1 hour from message creation for "delete for everyone"
     */
    public function destroy(Request $r, $messageId)
    {
        try {
            $message = Message::findOrFail($messageId);

            // Check if user can delete this message
            if ($message->sender_id !== $r->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only delete your own messages'
                ], 403);
            }

            $deleteFor = $r->input('delete_for', 'me'); // 'me' or 'everyone'

            // PHASE 1: Handle "delete for everyone"
            if ($deleteFor === 'everyone') {
                // Check if feature flag is enabled
                if (!\App\Services\FeatureFlagService::isEnabled('delete_for_everyone', $r->user())) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Delete for everyone feature is not available',
                    ], 403);
                }

                // Check time limit: 1 hour from message creation
                $timeLimit = now()->subHour();
                if ($message->created_at->lt($timeLimit)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Messages can only be deleted for everyone within 1 hour of sending',
                    ], 422);
                }

                // Check if already deleted for everyone
                if ($message->deleted_for_everyone_at) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Message already deleted for everyone',
                    ], 422);
                }

                // Check if this is a saved messages conversation (preserve even if deleted for everyone)
                $conversation = $message->conversation;
                $isSavedMessages = $conversation && !$conversation->is_group && 
                    $conversation->members()->count() === 1 &&
                    $conversation->members()->first()->id === $r->user()->id;

                // Set deleted_for_everyone_at timestamp
                $message->deleted_for_everyone_at = now();
                $message->save();

                // For "delete for everyone", we mark as deleted for ALL participants
                // (not just the sender's view)
                $participants = $conversation->members()->pluck('id');
                foreach ($participants as $participantId) {
                    // Mark as deleted for each participant
                    $message->statuses()->updateOrCreate(
                        ['user_id' => $participantId],
                        ['deleted_at' => now()]
                    );
                }

                // Exception: If saved messages, don't actually delete (preserve)
                if ($isSavedMessages) {
                    // Don't set deleted_for_everyone_at for saved messages
                    $message->deleted_for_everyone_at = null;
                    $message->save();
                    
                    // Only delete for the user themselves
                    $message->statuses()->updateOrCreate(
                        ['user_id' => $r->user()->id],
                        ['deleted_at' => now()]
                    );
                }

                // Broadcast deletion event to all participants
                broadcast(new \App\Events\MessageDeleted(
                    messageId: $message->id,
                    deletedBy: $r->user()->id,
                    conversationId: $message->conversation_id,
                    groupId: null,
                    deletedForEveryone: true
                ))->toOthers();

                return response()->json([
                    'success' => true,
                    'message' => 'Message deleted for everyone',
                    'deleted_for_everyone' => true,
                ]);
            }

            // Default: "Delete for me" (soft delete per-user)
            $message->statuses()->updateOrCreate(
                ['user_id' => $r->user()->id],
                ['deleted_at' => now()]
            );

            // Broadcast deletion event
            broadcast(new \App\Events\MessageDeleted(
                messageId: $message->id,
                deletedBy: $r->user()->id,
                conversationId: $message->conversation_id,
                groupId: null,
                deletedForEveryone: false
            ))->toOthers();

            return response()->json([
                'success' => true,
                'message' => 'Message deleted successfully',
                'deleted_for_everyone' => false,
            ]);
        } catch (\Exception $e) {
            \Log::error('Delete message error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete message'
            ], 500);
        }
    }

    /**
     * Share location in a conversation
     * POST /api/v1/conversations/{id}/share-location
     */
    public function shareLocation(Request $r, $conversationId)
    {
        $r->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
            'place_name' => 'nullable|string|max:255',
        ]);

        $conv = Conversation::findOrFail($conversationId);
        abort_unless($conv->isParticipant($r->user()->id), 403);

        $locationData = [
            'type' => 'location',
            'latitude' => $r->latitude,
            'longitude' => $r->longitude,
            'address' => $r->address,
            'place_name' => $r->place_name,
            'shared_at' => now()->toISOString(),
        ];

        $message = Message::create([
            'conversation_id' => $conv->id,
            'sender_id' => $r->user()->id,
            'body' => '📍 Shared location',
            'location_data' => $locationData,
            'is_encrypted' => false,
        ]);

        $message->load(['sender', 'attachments', 'reactions.user']);

        broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'success' => true,
            'data' => new MessageResource($message),
        ]);
    }

    /**
     * Share contact in a conversation
     * POST /api/v1/conversations/{id}/share-contact
     * 
     * Accepts either:
     * - contact_id (existing contact in database)
     * - OR direct contact data (name, phone, email) for mobile apps
     */
    public function shareContact(Request $r, $conversationId)
    {
        $conv = Conversation::findOrFail($conversationId);
        abort_unless($conv->isParticipant($r->user()->id), 403);

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

        $message = Message::create([
            'conversation_id' => $conv->id,
            'sender_id' => $r->user()->id,
            'body' => '👤 Shared contact',
            'contact_data' => $contactData,
            'is_encrypted' => false,
        ]);

        $message->load(['sender', 'attachments', 'reactions.user']);

        broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'success' => true,
            'data' => new MessageResource($message),
        ]);
    }
}
