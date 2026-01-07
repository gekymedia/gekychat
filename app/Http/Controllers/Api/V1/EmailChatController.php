<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\EmailMessage;
use App\Models\User;
use App\Services\EmailService;
use App\Services\FeatureFlagService;
use Illuminate\Http\Request;

/**
 * PHASE 2: Email Chat Controller
 * 
 * Handles viewing and replying to emails as chat messages.
 */
class EmailChatController extends Controller
{
    public function __construct(private EmailService $emailService)
    {
    }

    /**
     * Get mail tab conversations (email threads)
     * GET /api/v1/mail
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Check username requirement
        if (!$user->username) {
            return response()->json([
                'message' => 'Username is required to access Mail feature',
                'requires_username' => true,
            ], 403);
        }

        // Check feature flag
        if (!FeatureFlagService::isEnabled('email_chat', $user)) {
            return response()->json(['message' => 'Email chat feature is not available'], 403);
        }

        // Get conversations with email metadata
        $conversations = Conversation::whereHas('members', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->where(function ($query) {
            $query->whereJsonContains('metadata', ['email_conversation' => true])
                  ->orWhere('metadata->email_conversation', true);
        })
        ->with(['members', 'lastMessage'])
        ->orderBy('updated_at', 'desc')
        ->get();

        return response()->json([
            'data' => $conversations->map(function ($conversation) use ($user) {
                return [
                    'id' => $conversation->id,
                    'name' => $conversation->name,
                    'avatar_url' => null,
                    'last_message' => $conversation->lastMessage ? [
                        'body' => $conversation->lastMessage->body,
                        'created_at' => $conversation->lastMessage->created_at->toIso8601String(),
                    ] : null,
                    'is_email_conversation' => true,
                    'updated_at' => $conversation->updated_at->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Get messages in an email conversation
     * GET /api/v1/mail/conversations/{id}/messages
     */
    public function messages(Request $request, $conversationId)
    {
        $user = $request->user();

        if (!$user->username) {
            return response()->json(['message' => 'Username required'], 403);
        }

        $conversation = Conversation::findOrFail($conversationId);
        
        // Verify user is participant
        if (!$conversation->isParticipant($user->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $messages = $conversation->messages()
            ->with(['emailMessage', 'attachments', 'sender'])
            ->orderBy('created_at', 'asc')
            ->paginate($request->input('per_page', 50));

        $transformedMessages = $messages->map(function ($message) {
            $isEmail = !empty($message->metadata['email_source'] ?? false);
            
            return [
                'id' => $message->id,
                'body' => $message->body,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender ? $message->sender->name : ($message->emailMessage ? ($message->emailMessage->from_email['name'] ?? $message->emailMessage->from_email['address']) : 'Unknown'),
                'sender_email' => $isEmail && $message->emailMessage ? ($message->emailMessage->from_email['address'] ?? null) : null,
                'is_email' => $isEmail, // Frontend uses this to show @ indicator
                'subject' => $message->metadata['email_subject'] ?? null,
                'created_at' => $message->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $transformedMessages,
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Reply to an email conversation
     * POST /api/v1/mail/conversations/{conversationId}/reply
     */
    public function reply(Request $request, $conversationId)
    {
        $user = $request->user();

        if (!$user->username) {
            return response()->json(['message' => 'Username required'], 403);
        }

        $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $conversation = Conversation::findOrFail($conversationId);
        
        // Verify user is participant
        if (!$conversation->isParticipant($user->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get the last email message in this conversation to reply to
        $lastEmailMessage = $conversation->messages()
            ->whereHas('emailMessage')
            ->with('emailMessage')
            ->orderBy('created_at', 'desc')
            ->first();
        
        if (!$lastEmailMessage || !$lastEmailMessage->emailMessage) {
            return response()->json(['message' => 'No email message found to reply to'], 422);
        }

        // Create reply message in conversation
        $replyMessage = Message::create([
            'conversation_id' => $conversationId,
            'sender_id' => $user->id,
            'sender_type' => 'user',
            'body' => $request->body,
            'type' => 'text',
            'reply_to' => $lastEmailMessage->id,
        ]);

        // Send email reply
        $sent = $this->emailService->sendReplyEmail($replyMessage, $lastEmailMessage->emailMessage);

        if (!$sent) {
            return response()->json(['message' => 'Failed to send email reply'], 500);
        }

        // Broadcast message
        broadcast(new \App\Events\MessageSent($replyMessage))->toOthers();

        return response()->json([
            'message' => 'Reply sent',
            'data' => $replyMessage,
        ], 201);
    }

    /**
     * Check if user has username set
     * GET /api/v1/mail/check-username
     */
    public function checkUsername(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'has_username' => !empty($user->username),
            'username' => $user->username,
        ]);
    }
}
