<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Group;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index(Request $r)
    {
        $uid = $r->user()->id;
        $groups = Group::query()
            ->whereHas('members', fn($q)=>$q->where('users.id',$uid))
            ->withUnreadCountFor($uid)
            ->latest('updated_at')
            ->get();

        $data = $groups->map(function($g) use ($uid){
            $last = $g->messages()->visibleTo($uid)->latest()->first();
            return [
                'id' => $g->id,
                'type' => 'group',
                'name' => $g->name,
                'avatar' => $g->avatar_path ? asset('storage/'.$g->avatar_path) : null,
                'last_message' => $last ? [
                    'id'=>$last->id,
                    'body_preview'=>mb_strimwidth((string)$last->body,0,140,'…'),
                    'created_at'=>optional($last->created_at)->toIso8601String(),
                ]:null,
                'unread' => $g->unreadCountFor($uid),
            ];
        });

        return response()->json(['data'=>$data]);
    }

    public function show(Request $r, $id)
    {
        $g = Group::findOrFail($id);
        abort_unless($g->isMember($r->user()), 403);
        return response()->json(['data' => ['id'=>$g->id]]);
    }

    public function messages(Request $r, $id)
    {
        $r->validate(['before'=>'nullable|date','after'=>'nullable|date','limit'=>'nullable|integer|min:1|max:100']);
        $uid = $r->user()->id;
        $g = Group::findOrFail($id);
        abort_unless($g->isMember($r->user()), 403);

        $q = $g->messages()->with(['sender:id,name,phone,avatar_path','attachments','replyTo','forwardedFrom','reactions.user'])
            ->visibleTo($uid)->orderBy('created_at','desc');

        if ($r->filled('before')) $q->where('created_at','<',$r->before);
        if ($r->filled('after'))  $q->where('created_at','>',$r->after);
        $items = $q->limit($r->integer('limit',50))->get()->sortBy('created_at')->values();

        return response()->json(['data' => MessageResource::collection($items)]);
    }

    // GroupController@messages
// public function messages($id, Request $req) {
//     $q = $req->query('q');
//     $query = GroupMessage::where('group_id', $id)->with(['sender','reactions','attachments']);
//     if ($q) {
//         $query->where(function($w) use ($q){
//             $w->where('body','like',"%{$q}%")
//               ->orWhereHas('attachments', fn($a)=>$a->where('original_name','like',"%{$q}%"));
//         });
//     }
//     // keep your before/after/limit logic
//     return GroupMessageResource::collection($query->latest()->paginate(50));
// }

}
