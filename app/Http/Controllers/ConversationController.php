<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    // List my conversations (basic, with unread count & last message)
    public function index(Request $r) {
        $uid = $r->user()->id;

        $convs = Conversation::query()
            ->select('conversations.*')
            ->join('conversation_user as cu', 'cu.conversation_id', '=', 'conversations.id')
            ->where('cu.user_id', $uid)
            ->with([
                'lastMessage' => function($q){ $q->with('sender:id,name,avatar'); },
                'members:id,name,avatar'
            ])
            ->orderByDesc(DB::raw('IFNULL(cu.pinned_at, conversations.updated_at)'))
            ->limit(100) // you can paginate later
            ->get();

        // compute unread counts fast via last_read_message_id
        $pivot = DB::table('conversation_user')->where('user_id', $uid)->pluck('last_read_message_id','conversation_id');

        $data = $convs->map(function($c) use ($uid, $pivot) {
            $lastReadId = $pivot[$c->id] ?? null;
            $unread = DB::table('messages')
                ->where('conversation_id', $c->id)
                ->when($lastReadId, fn($q) => $q->where('id', '>', $lastReadId))
                ->where('sender_id', '!=', $uid)
                ->count();

            return [
                'id'          => $c->id,
                'is_group'    => (bool) $c->is_group,
                'name'        => $c->name,
                'avatar'      => $c->avatar,
                'members'     => $c->members->map(fn($m)=>['id'=>$m->id,'name'=>$m->name,'avatar'=>$m->avatar])->values(),
                'last_message'=> $c->lastMessage ? [
                    'id' => $c->lastMessage->id,
                    'body'=> $c->lastMessage->body,
                    'sender'=> ['id'=>$c->lastMessage->sender->id,'name'=>$c->lastMessage->sender->name],
                    'created_at' => $c->lastMessage->created_at
                ] : null,
                'unread'      => $unread,
            ];
        });

        return ApiResponse::data($data);
    }

    // Create 1:1 (user_id) or group (is_group,name,member_ids[])
    public function store(Request $r) {
        $uid = $r->user()->id;
        $data = $r->validate([
            'user_id'    => 'nullable|exists:users,id',
            'is_group'   => 'nullable|boolean',
            'name'       => 'nullable|string|max:120',
            'member_ids' => 'array'
        ]);

        if (!empty($data['user_id'])) {
            // 1:1: find or create
            $other = (int)$data['user_id'];
            $conv = Conversation::findOrCreateDirect($uid, $other);
            return ApiResponse::data($conv->fresh('members:id,name,avatar'));
        }

        // Group
        $conv = Conversation::create([
            'is_group'  => true,
            'name'      => $data['name'] ?? 'New Group',
            'created_by'=> $uid,
        ]);
        $members = array_unique(array_merge([$uid], array_map('intval', $data['member_ids'] ?? [])));
        $conv->members()->syncWithPivotValues($members, ['role' => 'member']);
        $conv->members()->updateExistingPivot($uid, ['role' => 'owner']);

        return ApiResponse::data($c = $conv->load('members:id,name,avatar'));
    }

    public function show(Request $r, int $id) {
        $uid = $r->user()->id;
        $isMember = DB::table('conversation_user')->where('conversation_id',$id)->where('user_id',$uid)->exists();
        abort_unless($isMember, 403);

        $conv = Conversation::with([
            'members:id,name,avatar',
            'lastMessage' => fn($q)=>$q->with('sender:id,name,avatar')
        ])->findOrFail($id);

        return ApiResponse::data($conv);
    }

    public function pin(Request $r, int $id) {
        $uid = $r->user()->id;
        DB::table('conversation_user')
            ->where('conversation_id',$id)->where('user_id',$uid)
            ->update(['pinned_at' => now()]);
        return ApiResponse::data(['ok'=>true]);
    }

    public function unpin(Request $r, int $id) {
        $uid = $r->user()->id;
        DB::table('conversation_user')
            ->where('conversation_id',$id)->where('user_id',$uid)
            ->update(['pinned_at' => null]);
        return ApiResponse::data(['ok'=>true]);
    }

    public function mute(Request $r, int $id) {
        $uid = $r->user()->id;
        $until = $r->input('until'); // optional ISO string
        DB::table('conversation_user')
            ->where('conversation_id',$id)->where('user_id',$uid)
            ->update(['muted_until' => $until ?: now()->addDays(7)]);
        return ApiResponse::data(['ok'=>true]);
    }
}
