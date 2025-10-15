<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $r)
    {
        $u = $r->user()->id;

        $convs = Conversation::query()
            ->where(fn($q)=>$q->where('user_one_id',$u)->orWhere('user_two_id',$u))
            ->with(['userOne:id,name,phone,avatar_path','userTwo:id,name,phone,avatar_path'])
            ->orderByDesc(
                Message::select('created_at')->whereColumn('messages.conversation_id','conversations.id')->latest()->take(1)
            )
            ->get();

        $data = $convs->map(function($c) use ($u) {
            $other = $c->user_one_id === $u ? $c->userTwo : $c->userOne;
            $last = $c->messages()->notExpired()->visibleTo($u)->latest()->first();
            $unread = $c->messages()->notExpired()->visibleTo($u)->whereNull('read_at')->where('sender_id','!=',$u)->count();

            return [
                'id' => $c->id,
                'type' => 'dm',
                'title' => $other?->name ?: 'DM #'.$c->id,
                'other_user' => $other ? [
                    'id'=>$other->id,
                    'name'=>$other->name ?? $other->phone,
                    'avatar' => $other->avatar_path ? asset('storage/'.$other->avatar_path) : null,
                ] : null,
                'last_message' => $last ? [
                    'id' => $last->id,
                    'body_preview' => mb_strimwidth($last->is_encrypted ? '[Encrypted]' : (string)$last->body, 0, 140, '…'),
                    'created_at' => optional($last->created_at)->toIso8601String(),
                ] : null,
                'unread' => $unread,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function start(Request $r)
    {
        $r->validate(['user_id' => 'required|exists:users,id|different:'.$r->user()->id]);
        $a = min($r->user()->id, (int)$r->user_id);
        $b = max($r->user()->id, (int)$r->user_id);
        $conv = Conversation::firstOrCreate(['user_one_id'=>$a,'user_two_id'=>$b]);
        return response()->json(['data' => ['id'=>$conv->id]]);
    }

    public function show(Request $r, $id)
    {
        $conv = Conversation::findOrFail($id);
        abort_unless($conv->isParticipant($r->user()->id), 403);
        return response()->json(['data' => ['id'=>$conv->id]]);
    }

    public function messages(Request $r, $id)
    {
        $r->validate(['before'=>'nullable|date','after'=>'nullable|date','limit'=>'nullable|integer|min:1|max:100']);
        $u = $r->user()->id;
        $conv = Conversation::findOrFail($id);
        abort_unless($conv->isParticipant($u), 403);

        $q = $conv->messages()->with(['sender:id,name,phone,avatar_path','attachments','replyTo','forwardedFrom','reactions.user'])
            ->notExpired()->visibleTo($u)->orderBy('created_at','desc');

        if ($r->filled('before')) $q->where('created_at','<',$r->before);
        if ($r->filled('after'))  $q->where('created_at','>',$r->after);
        $items = $q->limit($r->integer('limit',50))->get()->sortBy('created_at')->values();

        // lazy mark as read
        $conv->markMessagesAsRead($u);

        return response()->json(['data' => MessageResource::collection($items)]);
    }
    
}
