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
        abort_unless($conv->isParticipant($r->user()->id), 403);

        // IDEMPOTENCY: Check if message with client_id already exists
        if ($r->filled('client_id')) {
            $existing = Message::where('client_uuid', $r->client_id)
                ->where('conversation_id', $conv->id)
                ->where('sender_id', $r->user()->id)
                ->first();

            if ($existing) {
                $existing->load(['sender','attachments','replyTo','forwardedFrom','reactions.user']);
                return response()->json(['message' => $existing], 200); // Return existing
            }
        }

        if (!$r->filled('body') && !$r->filled('attachments') && !$r->filled('forward_from')) {
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

        if ($r->filled('attachments')) {
            Attachment::whereIn('id', $r->attachments)->update(['attachable_id'=>$msg->id, 'attachable_type'=>Message::class]);
        }

        $msg->load(['sender','attachments','replyTo','forwardedFrom','reactions.user']);
        
        // Mark as delivered for recipients
        $recipients = $conv->members()->where('users.id', '!=', $r->user()->id)->get();
        foreach ($recipients as $recipient) {
            $msg->markAsDeliveredFor($recipient->id);
        }

        broadcast(new MessageSent($msg))->toOthers();

        return response()->json(['message' => $msg], 201);
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

        broadcast(new MessageRead($conversationId, $r->message_ids, $r->user()->id))->toOthers();

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
        broadcast(new MessageRead($msg->conversation_id, [$msg->id], $r->user()->id))->toOthers();
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

        return response()->json([
            'messages' => $messages,
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
                        'file_name' => $attachment->file_name,
                        'file_path' => $attachment->file_path,
                        'file_type' => $attachment->file_type,
                        'file_size' => $attachment->file_size,
                        'mime_type' => $attachment->mime_type,
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
}
