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
    public function store(Request $r, $groupId)
    {
        $r->validate([
            'body' => 'nullable|string|max:5000',
            'reply_to_id' => 'nullable|integer|exists:group_messages,id',
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
            'reply_to_id' => $r->reply_to_id,
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
}
