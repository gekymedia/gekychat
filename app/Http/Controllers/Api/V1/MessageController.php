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
        broadcast(new MessageSent($msg))->toOthers();

        return response()->json(['data' => new MessageResource($msg)], 201);
    }

    public function markRead(Request $r, $messageId)
    {
        $msg = Message::findOrFail($messageId);
        abort_unless($msg->conversation->isParticipant($r->user()->id), 403);
        $msg->markAsReadFor($r->user()->id);
        broadcast(new MessageRead($msg->conversation_id, $msg->id, $r->user()->id))->toOthers();
        return response()->json(['ok'=>true]);
    }

    public function typing(Request $r, $conversationId)
    {
        $r->validate(['is_typing'=>'required|boolean']);
        $conv = Conversation::findOrFail($conversationId);
        abort_unless($conv->isParticipant($r->user()->id), 403);
        broadcast(new TypingInGroup((int)$conversationId, $r->user()->id, (bool)$r->is_typing))->toOthers();
        return response()->json(['ok'=>true]);
    }

    public function forwardToTargets(Request $r, $messageId)
    {
        $r->validate([
            'targets' => 'required|array|min:1',
            'targets.*.type' => 'required|in:conversation,group',
            'targets.*.id' => 'required|integer',
        ]);

        $msg = Message::with(['sender','attachments'])->findOrFail($messageId);
        abort_unless($msg->conversation->isParticipant($r->user()->id), 403);

        $results = app('App\\Services\\ForwardService')->forwardDmToTargets($msg, $r->user(), $r->targets);
        return response()->json(['status'=>'success','results'=>$results]);
    }
}
