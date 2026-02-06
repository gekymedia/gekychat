<?php
namespace App\Http\Controllers\Api\V1;

use App\Events\MessageSent;
use App\Events\MessageRead;
use App\Events\TypingInGroup;
use App\Events\ConversationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\PrivacyService;
use App\Services\TextFormattingService;
use App\Services\VideoUploadLimitService;
use App\Services\ForwardService;
use App\Services\MentionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\MessageStatus;

class MessageController extends Controller
{
    protected $mentionService;
    
    public function __construct(MentionService $mentionService)
    {
        $this->mentionService = $mentionService;
    }
    public function store(Request $r, $conversationId)
    {
        // Check if files are being uploaded before validation
        // Check for any file uploads in the request (attachments[], attachments, or any key containing 'attachment')
        $hasFileUploadsInRequest = $r->hasFile('attachments') || !empty($r->allFiles());
        
        // Additional check: if request has files with keys containing 'attachment', treat as file upload
        if (!$hasFileUploadsInRequest) {
            foreach (array_keys($r->allFiles()) as $key) {
                if (str_contains(strtolower($key), 'attachment')) {
                    $hasFileUploadsInRequest = true;
                    break;
                }
            }
        }
        
        $validationRules = [
            'client_id' => 'nullable|string|max:100',
            'client_message_id' => 'nullable|string|max:100', // Alternative name for client_id (for mobile apps)
            'client_uuid' => 'nullable|string|max:100', // Standard client UUID for offline-first support
            'body' => 'nullable|string',
            'reply_to' => 'nullable|exists:messages,id',
            'reply_to_id' => 'nullable|exists:messages,id', // Alternative name for reply_to (for mobile apps)
            'forward_from' => 'nullable|exists:messages,id',
            'is_encrypted' => 'nullable|boolean',
            'expires_in' => 'nullable|integer|min:0|max:168',
        ];
        
        // Only add attachments validation if no files are being uploaded
        // When files are uploaded via FormData (attachments[]), don't validate as integer array
        if (!$hasFileUploadsInRequest) {
            $validationRules['attachments'] = 'nullable|array';
            $validationRules['attachments.*'] = 'integer|exists:attachments,id';
        }
        
        $r->validate($validationRules);
        
        // Validate text formatting if body is provided
        if ($r->filled('body')) {
            $validation = TextFormattingService::validateFormatting($r->body);
            if (!$validation['valid']) {
                return response()->json([
                    'message' => 'Invalid text formatting: ' . implode(', ', $validation['errors']),
                    'errors' => $validation['errors'],
                ], 422);
            }
        }

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

        // IDEMPOTENCY: Check if message with client_uuid/client_id/client_message_id already exists
        $clientId = $r->input('client_uuid') ?? $r->input('client_message_id') ?? $r->input('client_id');
        if ($clientId) {
            $existing = Message::where('client_uuid', $clientId)
                ->where('conversation_id', $conv->id)
                ->where('sender_id', $r->user()->id)
                ->first();

            if ($existing) {
                $existing->load(['sender','attachments','replyTo','forwardedFrom','reactions.user']);
                return response()->json(['data' => new MessageResource($existing)], 200); // Return existing
            }
        }

        // Check for file uploads (attachments[] in form data) or attachment IDs
        // Mobile apps send files as 'attachments[]' which Laravel may receive in different formats
        $hasFileUploads = false;
        $uploadedFiles = [];
        
        // Method 1: Check for 'attachments' key (works when Laravel normalizes attachments[] to attachments)
        if ($r->hasFile('attachments')) {
            $files = $r->file('attachments');
            if (is_array($files)) {
                $uploadedFiles = array_filter($files, fn($f) => $f && $f->isValid());
            } elseif ($files && $files->isValid()) {
                $uploadedFiles = [$files];
            }
            $hasFileUploads = count($uploadedFiles) > 0;
            Log::debug('File upload detected via hasFile("attachments")', ['count' => count($uploadedFiles)]);
        }
        
        // Method 2: Check all files in request (for attachments[] array uploads from mobile)
        // This catches files sent as 'attachments[]' that Laravel receives as array
        if (!$hasFileUploads) {
            $allFiles = $r->allFiles();
            Log::debug('Checking allFiles() for attachments', ['keys' => array_keys($allFiles), 'count' => count($allFiles)]);
            
            foreach ($allFiles as $key => $file) {
                // Check if key contains 'attachment' (catches 'attachments', 'attachments[]', etc.)
                $keyLower = strtolower($key);
                if (str_contains($keyLower, 'attachment')) {
                    if (is_array($file)) {
                        // Multiple files in array
                        $validFiles = array_filter($file, fn($f) => $f && (is_string($f) || ($f instanceof \Illuminate\Http\UploadedFile && $f->isValid())));
                        if (!empty($validFiles)) {
                            // Filter out string paths (these shouldn't happen, but be safe)
                            $fileObjects = array_filter($validFiles, fn($f) => $f instanceof \Illuminate\Http\UploadedFile);
                            if (!empty($fileObjects)) {
                                $uploadedFiles = array_merge($uploadedFiles, $fileObjects);
                            }
                        }
                    } elseif ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                        // Single file
                        $uploadedFiles[] = $file;
                    }
                }
            }
            $hasFileUploads = count($uploadedFiles) > 0;
            if ($hasFileUploads) {
                Log::debug('File upload detected via allFiles()', ['count' => count($uploadedFiles), 'keys_found' => array_keys($allFiles)]);
            }
        }
        
        // Method 3: Check request input for file data (fallback for edge cases)
        if (!$hasFileUploads && $r->has('attachments')) {
            $attachmentsInput = $r->input('attachments');
            if (is_array($attachmentsInput)) {
                // This might be file objects or file paths
                foreach ($attachmentsInput as $item) {
                    if ($item instanceof \Illuminate\Http\UploadedFile && $item->isValid()) {
                        $uploadedFiles[] = $item;
                        $hasFileUploads = true;
                    }
                }
                if ($hasFileUploads) {
                    Log::debug('File upload detected via input("attachments")', ['count' => count($uploadedFiles)]);
                }
            }
        }
        
        // Final validation: ensure we have valid uploaded files
        $uploadedFiles = array_filter($uploadedFiles, fn($f) => $f instanceof \Illuminate\Http\UploadedFile && $f->isValid());
        $hasFileUploads = count($uploadedFiles) > 0;
        
        Log::info('File upload check result', [
            'has_file_uploads' => $hasFileUploads,
            'file_count' => count($uploadedFiles),
            'has_body' => $r->filled('body'),
            'has_forward_from' => $r->filled('forward_from'),
        ]);
        
        // Check for attachment IDs (pre-uploaded attachments referenced by ID)
        $hasAttachmentIds = false;
        $attachmentIds = [];
        
        // Check if 'attachments' is an array of integers (attachment IDs, not files)
        if ($r->filled('attachments') && is_array($r->attachments)) {
            // Filter out UploadedFile objects (those are handled above)
            $ids = array_filter($r->attachments, fn($item) => is_numeric($item) || is_int($item));
            if (!empty($ids)) {
                $attachmentIds = array_values(array_map('intval', $ids));
                $hasAttachmentIds = count($attachmentIds) > 0;
            }
        }
        
        // Validation: At least one of body, files, attachment IDs, or forward_from must be present
        if (!$r->filled('body') && !$hasFileUploads && !$hasAttachmentIds && !$r->filled('forward_from')) {
            Log::warning('Message validation failed: No content provided', [
                'has_body' => $r->filled('body'),
                'has_file_uploads' => $hasFileUploads,
                'has_attachment_ids' => $hasAttachmentIds,
                'has_forward_from' => $r->filled('forward_from'),
                'all_files_keys' => array_keys($r->allFiles()),
                'request_has_attachments' => $r->has('attachments'),
            ]);
            return response()->json([
                'message' => 'Type a message, attach a file, or forward a message.',
                'error' => 'No content provided',
            ], 422);
        }

        // Validate chat video uploads BEFORE creating message (size limit only, no duration limit)
        if ($hasFileUploads && !empty($uploadedFiles)) {
            $limitService = app(VideoUploadLimitService::class);
            
            foreach ($uploadedFiles as $file) {
                if ($file && $file->isValid()) {
                    try {
                        $mimeType = $file->getMimeType();
                        $isVideo = str_starts_with($mimeType, 'video/');
                        
                        if ($isVideo) {
                            $validation = $limitService->validateChatVideo($file, $r->user()->id);
                            
                            if (!$validation['valid']) {
                                Log::warning('Video validation failed', [
                                    'file_name' => $file->getClientOriginalName(),
                                    'file_size' => $file->getSize(),
                                    'error' => $validation['error'] ?? 'Unknown error',
                                ]);
                                return response()->json([
                                    'message' => $validation['error'] ?? 'Video file validation failed',
                                    'error' => 'video_validation_failed',
                                ], 422);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error validating video file', [
                            'error' => $e->getMessage(),
                            'file_name' => $file ? $file->getClientOriginalName() : 'unknown',
                        ]);
                        return response()->json([
                            'message' => 'Error validating video file: ' . $e->getMessage(),
                            'error' => 'video_validation_error',
                        ], 422);
                    }
                }
            }
        }

        $expiresAt = $r->filled('expires_in') && (int)$r->expires_in>0 ? now()->addHours((int)$r->expires_in) : null;

        $forwardChain = null;
        if ($r->filled('forward_from')) {
            $orig = Message::with('sender')->find($r->forward_from);
            $forwardChain = $orig ? $orig->buildForwardChain() : null;
        }

        // Support both reply_to and reply_to_id
        $replyTo = $r->input('reply_to_id') ?? $r->input('reply_to');
        
        $msg = Message::create([
            'client_uuid' => $clientId ?? \Illuminate\Support\Str::uuid(),
            'conversation_id' => $conv->id,
            'sender_id' => $r->user()->id,
            'body' => (string)($r->body ?? ''),
            'reply_to' => $replyTo,
            'forwarded_from_id' => $r->forward_from,
            'forward_chain' => $forwardChain,
            'is_encrypted' => (bool)($r->is_encrypted ?? false),
            'expires_at' => $expiresAt,
        ]);

        // Handle file uploads (attachments[] as files)
        if ($hasFileUploads && !empty($uploadedFiles)) {
            // Get as_document flags if provided (indicates sharing intent - document vs image/video)
            // WhatsApp-style: images shared as documents should be treated as documents
            $asDocumentArray = $r->input('as_document', []);
            if (!is_array($asDocumentArray)) {
                $asDocumentArray = [];
            }
            
            foreach ($uploadedFiles as $index => $file) {
                try {
                    if ($file && $file->isValid()) {
                        $path = $file->store('attachments', 'public');
                        if (!$path) {
                            Log::error('Failed to store file', [
                                'original_name' => $file->getClientOriginalName(),
                                'size' => $file->getSize(),
                            ]);
                            continue;
                        }
                        
                        // Check if this file was shared as a document
                        $isDocument = isset($asDocumentArray[$index]) && ($asDocumentArray[$index] === '1' || $asDocumentArray[$index] === 1 || $asDocumentArray[$index] === true);
                        $mimeType = $file->getClientMimeType();
                        
                        // Check if this is a voice note (from request flag or mime type)
                        $isVoicenoteArray = $r->input('is_voicenote', []);
                        $isVoicenote = false;
                        if (is_array($isVoicenoteArray) && isset($isVoicenoteArray[$index])) {
                            $isVoicenote = ($isVoicenoteArray[$index] === '1' || $isVoicenoteArray[$index] === 1 || $isVoicenoteArray[$index] === true);
                        } else {
                            // Fallback: check mime type for audio files (voice notes are typically audio/m4a, audio/aac, audio/mpeg)
                            $isVoicenote = str_starts_with($mimeType, 'audio/') && !$isDocument;
                        }
                        
                        $attachment = Attachment::create([
                            'user_id' => $r->user()->id,
                            'attachable_id' => $msg->id,
                            'attachable_type' => Message::class,
                            'file_path' => $path,
                            'original_name' => $file->getClientOriginalName(),
                            'mime_type' => $mimeType,
                            'shared_as_document' => $isDocument, // Store sharing intent (WhatsApp-style)
                            'is_voicenote' => $isVoicenote, // Mark as voice note
                            'size' => $file->getSize(),
                        ]);
                        
                        // Only compress images that were NOT shared as documents
                        // Images shared as documents should be displayed as documents, not compressed
                        // Audio and video files are never compressed
                        if (!$isDocument && str_starts_with($mimeType, 'image/')) {
                            \App\Jobs\CompressImage::dispatch($attachment);
                        }
                        
                        Log::debug('File attachment created', [
                            'attachment_id' => $attachment->id,
                            'file_path' => $path,
                            'original_name' => $file->getClientOriginalName(),
                            'shared_as_document' => $isDocument,
                            'mime_type' => $mimeType,
                            'will_compress' => !$isDocument && str_starts_with($mimeType, 'image/'),
                        ]);
                    } else {
                        Log::warning('Invalid file skipped during upload', [
                            'file' => $file ? get_class($file) : 'null',
                            'is_valid' => $file ? $file->isValid() : false,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing file upload', [
                        'error' => $e->getMessage(),
                        'file_name' => $file ? $file->getClientOriginalName() : 'unknown',
                    ]);
                    // Continue with other files even if one fails
                }
            }
        }
        
        // Handle attachment IDs (pre-uploaded attachments)
        if ($hasAttachmentIds && !empty($attachmentIds)) {
            Attachment::whereIn('id', $attachmentIds)->update(['attachable_id'=>$msg->id, 'attachable_type'=>Message::class]);
        }

        $msg->load(['sender','attachments','replyTo','forwardedFrom','reactions.user']);
        
        // NEW: Process @mentions in message body
        if (!empty($msg->body)) {
            try {
                $mentionsCreated = $this->mentionService->createMentions(
                    $msg,
                    $r->user()->id,
                    null // null for 1-on-1 conversations
                );
                
                if ($mentionsCreated > 0) {
                    Log::info("Created {$mentionsCreated} mentions in message #{$msg->id}");
                    // Reload to include mentions in response
                    $msg->load('mentions');
                }
            } catch (\Exception $e) {
                Log::error('Error processing mentions', [
                    'message_id' => $msg->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
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
     * If message_ids is provided, mark those specific messages.
     * Otherwise, mark all unread messages in the conversation.
     */
    public function markConversationRead(Request $r, $conversationId)
    {
        $r->validate([
            'message_ids' => 'sometimes|array',
            'message_ids.*' => 'integer|exists:messages,id',
        ]);

        $conv = Conversation::findOrFail($conversationId);
        abort_unless($conv->isParticipant($r->user()->id), 403);

        $userId = $r->user()->id;

        // If message_ids provided, mark those specific messages
        // Otherwise, mark all unread messages in the conversation
        if ($r->has('message_ids') && !empty($r->message_ids)) {
            $messageIds = $r->message_ids;
        } else {
            // Get all unread messages for this user in this conversation
            $messageIds = $conv->messages()
                ->where('sender_id', '!=', $userId)
                ->whereDoesntHave('statuses', function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->where('status', \App\Models\MessageStatus::STATUS_READ)
                        ->whereNull('deleted_at'); // Exclude soft-deleted statuses
                })
                ->pluck('id')
                ->toArray();
        }

        $markedCount = 0;
        if (!empty($messageIds)) {
            // Get the highest message ID to mark as last read
            $maxMessageId = Message::whereIn('id', $messageIds)
                ->where('conversation_id', $conversationId)
                ->max('id');

            if ($maxMessageId) {
                // Update the pivot table
                $conv->members()->updateExistingPivot($userId, [
                    'last_read_message_id' => $maxMessageId
                ]);
            }

            // Mark each message as read
            foreach ($messageIds as $messageId) {
                $msg = Message::find($messageId);
                if ($msg && $msg->conversation_id == $conversationId) {
                    $msg->markAsReadFor($userId);
                    $markedCount++;
                }
            }

            // Check privacy setting: if user has disable_read_receipts enabled, don't broadcast
            if (PrivacyService::shouldSendReadReceipt($r->user())) {
                broadcast(new MessageRead($conversationId, $userId, $messageIds))->toOthers();
            }
        } else {
            // No unread messages, but still update last_read_message_id to latest
            $latestMessageId = $conv->messages()->max('id');
            if ($latestMessageId) {
                $conv->members()->updateExistingPivot($userId, [
                    'last_read_message_id' => $latestMessageId
                ]);
            }
        }

        // Get updated unread count after marking as read
        $updatedUnreadCount = $conv->unreadCountFor($userId);
        
        // Broadcast conversation update event to notify all clients
        broadcast(new ConversationUpdated($conv->id, [
            'unread_count' => $updatedUnreadCount,
            'last_read_message_id' => $maxMessageId ?? $latestMessageId ?? null,
        ]))->toOthers();
        
        return response()->json([
            'success' => true,
            'marked_count' => $markedCount,
            'unread_count' => $updatedUnreadCount,
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
        
        // Check privacy setting: if user has disable_read_receipts enabled, don't broadcast
        if (PrivacyService::shouldSendReadReceipt($r->user())) {
            broadcast(new MessageRead($msg->conversation_id, $r->user()->id, [$msg->id]))->toOthers();
        }
        
        return response()->json(['success'=>true]);
    }

    /**
     * Get message info (readers, delivered, sent status) - WhatsApp style
     * GET /api/v1/messages/{id}/info
     */
    public function info(Request $r, $messageId)
    {
        $message = Message::findOrFail($messageId);
        $userId = $r->user()->id;
        
        // Only sender can see message info
        abort_unless($message->sender_id === $userId, 403, 'Only message sender can view message info');
        
        // Check if user is participant in conversation
        abort_unless($message->conversation->isParticipant($userId), 403);
        
        // Get all statuses with user info
        $statuses = $message->statuses()
            ->with('user:id,name,avatar_path')
            ->get();
        
        // Get conversation members to know total count
        $conversation = $message->conversation;
        $members = $conversation->members()->where('users.id', '!=', $userId)->pluck('users.id');
        $totalRecipients = $members->count();
        
        // Group statuses by type
        $sent = $statuses->where('status', MessageStatus::STATUS_SENT)->values();
        $delivered = $statuses->where('status', MessageStatus::STATUS_DELIVERED)->values();
        $read = $statuses->where('status', MessageStatus::STATUS_READ)->values();
        
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

    public function typing(Request $r, $conversationId)
    {
        $r->validate(['is_typing'=>'required|boolean']);
        $conv = Conversation::findOrFail($conversationId);
        abort_unless($conv->isParticipant($r->user()->id), 403);
        
        // Check privacy setting: if user has hide_typing enabled, don't broadcast
        if (!PrivacyService::shouldBroadcastTyping($r->user())) {
            // User has typing privacy enabled, return success but don't broadcast
            return response()->json(['ok'=>true, 'broadcasted'=>false]);
        }
        
        // Use UserTyping event for conversations (1-on-1 chats)
        broadcast(new \App\Events\UserTyping((int)$conversationId, null, $r->user()->id, (bool)$r->is_typing))->toOthers();
        return response()->json(['ok'=>true, 'broadcasted'=>true]);
    }

    /**
     * Get messages in a conversation with pagination and incremental sync
     * GET /api/v1/conversations/{id}/messages
     * 
     * Query parameters:
     * - limit: Maximum number of messages (default: 50, max: 500)
     * - before: Message ID - return messages before this ID (for pagination)
     * - after: Message ID - return messages after this ID (for incremental sync)
     * - after_id: Message ID - return messages after this ID (alternative to after)
     * - after_timestamp: ISO 8601 timestamp - return messages after this timestamp (for incremental sync)
     */
    public function index(Request $r, $conversationId)
    {
        $r->validate([
            'limit' => 'nullable|integer|min:1|max:500',
            'before' => 'nullable|integer|exists:messages,id',
            'after' => 'nullable|integer|exists:messages,id',
            'after_id' => 'nullable|integer|exists:messages,id',
            'after_timestamp' => 'nullable|date',
        ]);

        $conv = Conversation::findOrFail($conversationId);
        abort_unless($conv->isParticipant($r->user()->id), 403);

        // PERFORMANCE FIX: Use smaller default limit for faster initial load
        // Mobile apps can request more if needed
        $limit = min($r->input('limit', 30), 500); // Max 500 messages per request, default 30
        
        // PERFORMANCE FIX: Optimize eager loading - only load necessary relations
        $query = Message::where('conversation_id', $conversationId)
            ->with([
                'sender:id,name,phone,username,avatar_path', // Only select needed columns
                'attachments:id,attachable_id,attachable_type,file_path,original_name,mime_type,size', // Polymorphic columns
                'replyTo:id,body,sender_id', // Minimal data for reply
                'replyTo.sender:id,name,phone', // Minimal sender data for reply
                'reactions' => function($q) {
                    // message_reactions table uses `message_id` and `reaction` columns
                    $q->select('id', 'message_id', 'user_id', 'reaction')->limit(20);
                },
                'reactions.user:id,name,avatar_path',
            ])
            ->orderBy('id', 'asc'); // Changed to asc for incremental sync (oldest first)

        // Incremental sync: after message ID (preferred method)
        if ($r->filled('after_id')) {
            $query->where('id', '>', $r->after_id);
        } elseif ($r->filled('after')) {
            // Backward compatibility: 'after' also means after_id
            $query->where('id', '>', $r->after);
        } elseif ($r->filled('after_timestamp')) {
            // Incremental sync: after timestamp (ISO 8601)
            try {
                $afterTimestamp = \Carbon\Carbon::parse($r->after_timestamp);
                $query->where(function($q) use ($afterTimestamp, $r) {
                    $q->where('created_at', '>', $afterTimestamp)
                      ->orWhere(function($q2) use ($afterTimestamp, $r) {
                          // If same timestamp, use ID comparison
                          $q2->where('created_at', '=', $afterTimestamp)
                             ->where('id', '>', $r->get('after_id', 0));
                      });
                });
            } catch (\Exception $e) {
                \Log::warning('Invalid after_timestamp format: ' . $r->after_timestamp);
            }
        }

        // Pagination: before message ID (for loading older messages)
        if ($r->filled('before')) {
            $query->where('id', '<', $r->before);
            // For 'before' pagination, we want descending order
            $query->orderBy('id', 'desc');
        }

        // PERFORMANCE FIX: Fetch limit+1 to check if there are more messages
        // This avoids expensive count() query on large conversations
        $messages = $query->limit($limit + 1)->get();
        
        // Check if there are more messages
        $hasMore = $messages->count() > $limit;
        
        // Remove the extra message if we fetched limit+1
        if ($hasMore) {
            $messages = $messages->take($limit);
        }
        
        // If using 'before', reverse to show oldest first in the result
        if ($r->filled('before')) {
            $messages = $messages->reverse()->values();
        }
        
        // PERFORMANCE FIX: Mark messages as delivered in bulk (async job would be even better)
        // Only mark messages from other users
        $messagesToMarkDelivered = $messages->filter(function($msg) use ($r) {
            return $msg->sender_id !== $r->user()->id && $msg->delivered_at === null;
        })->pluck('id');
        
        if ($messagesToMarkDelivered->isNotEmpty()) {
            // Replace legacy `delivered_at` column update with per-user MessageStatus rows.
            // For each message mark a MessageStatus for the current user as 'delivered'
            $userId = $r->user()->id;
            $messageIds = $messagesToMarkDelivered->values()->all();

            // Load existing statuses for these messages for this user
            $existing = MessageStatus::whereIn('message_id', $messageIds)
                ->where('user_id', $userId)
                ->get()
                ->keyBy('message_id');

            foreach ($messageIds as $mid) {
                $status = $existing->get($mid);
                if ($status) {
                    // Only upgrade 'sent' -> 'delivered'; leave 'read' intact
                    if ($status->status === MessageStatus::STATUS_SENT) {
                        $status->status = MessageStatus::STATUS_DELIVERED;
                        $status->save();
                    }
                } else {
                    // Use updateOrCreate to avoid duplicate key when concurrent requests insert same row
                    MessageStatus::updateOrCreate(
                        [
                            'message_id' => $mid,
                            'user_id' => $userId,
                        ],
                        [
                            'status' => MessageStatus::STATUS_DELIVERED,
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }

        // Return in consistent format with MessageResource
        return response()->json([
            'data' => MessageResource::collection($messages),
            'has_more' => $hasMore,
            'meta' => [
                'has_more' => $hasMore,
                'last_id' => $messages->isNotEmpty() ? $messages->last()->id : null,
                'count' => $messages->count(),
            ],
        ]);
    }

    /**
     * Forward message to multiple conversations
     * POST /api/v1/messages/{id}/forward
     */
    public function forward(Request $r, $messageId)
    {
        // Support both old format (conversation_ids) and new format (targets with type/id)
        $targets = [];
        if ($r->has('targets')) {
            $r->validate([
                'targets' => 'required|array|min:1',
                'targets.*.type' => 'required|string|in:conversation,group',
                'targets.*.id' => 'required|integer',
            ]);
            $targets = $r->input('targets');
        } else {
            $r->validate([
                'conversation_ids' => 'required|array|min:1',
                'conversation_ids.*' => 'integer|exists:conversations,id',
            ]);
            // Convert old format to new format
            foreach ($r->input('conversation_ids') as $convId) {
                $targets[] = ['type' => 'conversation', 'id' => $convId];
            }
        }

        $msg = Message::with(['sender','attachments'])->findOrFail($messageId);
        
        // Check if user has access to the original message
        // User can forward if they are a participant in the conversation OR if they sent the message
        $hasAccess = $msg->sender_id === $r->user()->id || 
                     $msg->conversation->isParticipant($r->user()->id);
        
        if (!$hasAccess) {
            Log::warning('Forward denied: User not participant', [
                'user_id' => $r->user()->id,
                'message_id' => $messageId,
                'conversation_id' => $msg->conversation_id,
            ]);
        }
        
        abort_unless($hasAccess, 403, 'You do not have permission to forward this message');

        $newMessageIds = [];

        foreach ($targets as $target) {
            $targetType = $target['type'];
            $targetId = $target['id'];

            if ($targetType === 'conversation') {
                $targetConv = Conversation::findOrFail($targetId);
                
                // Check if user can access target conversation
                if (!$targetConv->isParticipant($r->user()->id)) {
                    continue;
                }

                // Create forwarded message
                $forwardedMsg = Message::create([
                    'client_uuid' => \Illuminate\Support\Str::uuid(),
                    'conversation_id' => $targetId,
                    'sender_id' => $r->user()->id,
                    'body' => $msg->body,
                    'forwarded_from_id' => $msg->id,
                    'forward_chain' => $msg->buildForwardChain(),
                ]);

                // Copy attachments (create new file copies, not just references)
                if ($msg->attachments->isNotEmpty()) {
                    foreach ($msg->attachments as $attachment) {
                        $newPath = null;
                        
                        // Copy the physical file to a new location
                        try {
                            $ext = pathinfo($attachment->file_path, PATHINFO_EXTENSION);
                            $newPath = 'attachments/' . uniqid('fwd_', true) . ($ext ? ('.' . $ext) : '');
                            
                            // Copy the file if it exists
                            if (Storage::disk('public')->exists($attachment->file_path)) {
                                Storage::disk('public')->copy($attachment->file_path, $newPath);
                            } else {
                                // If original file doesn't exist, log warning but continue
                                Log::warning('Original attachment file not found during forward', [
                                    'original_path' => $attachment->file_path,
                                    'attachment_id' => $attachment->id,
                                    'message_id' => $msg->id,
                                ]);
                                // Use original path as fallback (may cause issues, but better than failing)
                                $newPath = $attachment->file_path;
                            }
                        } catch (\Throwable $e) {
                            Log::error('Failed to copy attachment file during forward', [
                                'error' => $e->getMessage(),
                                'original_path' => $attachment->file_path,
                                'attachment_id' => $attachment->id,
                            ]);
                            // Use original path as fallback
                            $newPath = $attachment->file_path;
                        }
                        
                        Attachment::create([
                            'user_id' => $r->user()->id, // Set user_id to the person forwarding
                            'attachable_type' => Message::class,
                            'attachable_id' => $forwardedMsg->id,
                            'original_name' => $attachment->original_name,
                            'file_path' => $newPath,
                            'mime_type' => $attachment->mime_type,
                            'size' => $attachment->size,
                            'shared_as_document' => $attachment->shared_as_document,
                            'is_voicenote' => $attachment->is_voicenote,
                            // Copy compression fields if they exist
                            'compression_status' => $attachment->compression_status,
                            'compressed_file_path' => $attachment->compressed_file_path,
                            'thumbnail_path' => $attachment->thumbnail_path,
                            'original_size' => $attachment->original_size,
                            'compressed_size' => $attachment->compressed_size,
                            'compression_level' => $attachment->compression_level,
                        ]);
                    }
                }

                $forwardedMsg->load(['sender','attachments','replyTo','forwardedFrom','reactions.user']);
                broadcast(new MessageSent($forwardedMsg))->toOthers();

                $newMessageIds[] = $forwardedMsg->id;
            } elseif ($targetType === 'group') {
                // Forward to group
                $targetGroup = \App\Models\Group::findOrFail($targetId);
                
                // Check if user is a member of the target group
                if (!$targetGroup->members()->where('users.id', $r->user()->id)->exists()) {
                    continue;
                }

                // Create forwarded group message
                $forwardedMsg = \App\Models\GroupMessage::create([
                    'client_uuid' => \Illuminate\Support\Str::uuid(),
                    'group_id' => $targetId,
                    'sender_id' => $r->user()->id,
                    'body' => $msg->body,
                    'forwarded_from_id' => $msg->id,
                    'forward_chain' => $msg->buildForwardChain(),
                ]);

                // Copy attachments (create new file copies, not just references)
                if ($msg->attachments->isNotEmpty()) {
                    foreach ($msg->attachments as $attachment) {
                        $newPath = null;
                        
                        // Copy the physical file to a new location
                        try {
                            $ext = pathinfo($attachment->file_path, PATHINFO_EXTENSION);
                            $newPath = 'attachments/' . uniqid('fwd_', true) . ($ext ? ('.' . $ext) : '');
                            
                            // Copy the file if it exists
                            if (Storage::disk('public')->exists($attachment->file_path)) {
                                Storage::disk('public')->copy($attachment->file_path, $newPath);
                            } else {
                                // If original file doesn't exist, log warning but continue
                                Log::warning('Original attachment file not found during forward to group', [
                                    'original_path' => $attachment->file_path,
                                    'attachment_id' => $attachment->id,
                                    'message_id' => $msg->id,
                                ]);
                                // Use original path as fallback (may cause issues, but better than failing)
                                $newPath = $attachment->file_path;
                            }
                        } catch (\Throwable $e) {
                            Log::error('Failed to copy attachment file during forward to group', [
                                'error' => $e->getMessage(),
                                'original_path' => $attachment->file_path,
                                'attachment_id' => $attachment->id,
                            ]);
                            // Use original path as fallback
                            $newPath = $attachment->file_path;
                        }
                        
                        Attachment::create([
                            'user_id' => $r->user()->id, // Set user_id to the person forwarding
                            'attachable_type' => \App\Models\GroupMessage::class,
                            'attachable_id' => $forwardedMsg->id,
                            'original_name' => $attachment->original_name,
                            'file_path' => $newPath,
                            'mime_type' => $attachment->mime_type,
                            'size' => $attachment->size,
                            'shared_as_document' => $attachment->shared_as_document,
                            'is_voicenote' => $attachment->is_voicenote,
                            // Copy compression fields if they exist
                            'compression_status' => $attachment->compression_status,
                            'compressed_file_path' => $attachment->compressed_file_path,
                            'thumbnail_path' => $attachment->thumbnail_path,
                            'original_size' => $attachment->original_size,
                            'compressed_size' => $attachment->compressed_size,
                            'compression_level' => $attachment->compression_level,
                        ]);
                    }
                }

                $forwardedMsg->load(['sender','attachments','replyTo','forwardedFrom','reactions.user']);
                broadcast(new \App\Events\GroupMessageSent($forwardedMsg))->toOthers();

                $newMessageIds[] = $forwardedMsg->id;
            }
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
        
        // Validate text formatting
        $validation = TextFormattingService::validateFormatting($r->body);
        if (!$validation['valid']) {
            return response()->json([
                'message' => 'Invalid text formatting: ' . implode(', ', $validation['errors']),
                'errors' => $validation['errors'],
            ], 422);
        }

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
