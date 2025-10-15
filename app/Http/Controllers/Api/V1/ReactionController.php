<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\GroupMessage;
use App\Models\GroupMessageReaction;
use App\Models\Message;
use App\Models\MessageReaction;
use Illuminate\Http\Request;

class ReactionController extends Controller
{
    public function reactToMessage(Request $r, $messageId)
    {
        $r->validate(['emoji'=>'required|string|max:16']);
        $m = Message::with(['reactions.user','sender','attachments'])->findOrFail($messageId);
        abort_unless($m->conversation->isParticipant($r->user()->id), 403);

        MessageReaction::updateOrCreate(
            ['user_id'=>$r->user()->id,'message_id'=>$m->id],
            ['emoji'=>$r->emoji]
        );

        $m->load(['reactions.user']);
        return response()->json(['data'=>new MessageResource($m)]);
    }

    public function unreactFromMessage(Request $r, $messageId)
    {
        $m = Message::findOrFail($messageId);
        abort_unless($m->conversation->isParticipant($r->user()->id), 403);
        MessageReaction::where(['user_id'=>$r->user()->id,'message_id'=>$m->id])->delete();
        $m->load(['reactions.user','sender','attachments']);
        return response()->json(['data'=>new MessageResource($m)]);
    }

    public function reactToGroupMessage(Request $r, $id)
    {
        $r->validate(['emoji'=>'required|string|max:16']);
        $m = GroupMessage::with(['reactions.user','sender','attachments','group.members'])->findOrFail($id);
        abort_unless($m->group->isMember($r->user()), 403);

        GroupMessageReaction::updateOrCreate(
            ['user_id'=>$r->user()->id,'group_message_id'=>$m->id],
            ['emoji'=>$r->emoji]
        );

        $m->load(['reactions.user']);
        return response()->json(['data'=>new MessageResource($m)]);
    }

    public function unreactFromGroupMessage(Request $r, $id)
    {
        $m = GroupMessage::with(['group'])->findOrFail($id);
        abort_unless($m->group->isMember($r->user()), 403);
        GroupMessageReaction::where(['user_id'=>$r->user()->id,'group_message_id'=>$m->id])->delete();
        $m->load(['reactions.user','sender','attachments']);
        return response()->json(['data'=>new MessageResource($m)]);
    }
}
