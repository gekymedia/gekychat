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
            ->withUnreadCounts($uid)  // Use the correct scope name
            ->latest('updated_at')
            ->get();

        $now = now();
        $data = $groups->map(function($g) use ($uid, $now){
            $last = $g->messages()->visibleTo($uid)->latest()->first();
            // Determine pinned/muted from group_members pivot
            $memberPivot = $g->members()->where('users.id', $uid)->first()?->pivot;
            $isPinned = false;
            $isMuted = false;
            if ($memberPivot) {
                $isPinned = !is_null($memberPivot->pinned_at);
                $isMuted = $memberPivot->muted_until && $memberPivot->muted_until->gt($now);
            }
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
                'unread' => $g->getUnreadCountForUser($uid),
                'pinned' => $isPinned,
                'muted' => $isMuted,
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

    /**
     * Join a public group (channel). Only works if the group is not private.
     * POST /groups/{id}/join
     */
    public function join(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user  = $request->user();
        // Group must exist and be public
        abort_if($group->is_private, 403, 'Private groups cannot be joined without invitation.');

        // Add user if not already a member
        if (!$group->isMember($user)) {
            $group->members()->syncWithoutDetaching([
                $user->id => [
                    'role'      => 'member',
                    'joined_at' => now(),
                ],
            ]);
        }

        return response()->json([
            'status'   => 'success',
            'group_id' => $group->id,
            'joined'   => true,
        ]);
    }

    /**
     * Leave a group. Detach membership for current user. Owners cannot leave their own group.
     * DELETE /groups/{id}/leave
     */
    public function leave(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user  = $request->user();
        abort_unless($group->isMember($user), 403);
        // Owner cannot leave
        if ($group->owner_id === $user->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Group owner cannot leave their own group.',
            ], 422);
        }
        $group->members()->detach($user->id);

        return response()->json([
            'status'   => 'success',
            'group_id' => $group->id,
            'left'     => true,
        ]);
    }

    /**
     * Pin a group conversation for the current user. Sets pinned_at on pivot.
     * POST /groups/{id}/pin
     */
    public function pin(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user  = $request->user();
        abort_unless($group->isMember($user), 403);
        $group->members()->updateExistingPivot($user->id, [
            'pinned_at' => now(),
        ]);
        return response()->json([
            'status'    => 'success',
            'pinned_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Unpin a group conversation for the current user.
     * DELETE /groups/{id}/pin
     */
    public function unpin(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user  = $request->user();
        abort_unless($group->isMember($user), 403);
        $group->members()->updateExistingPivot($user->id, [
            'pinned_at' => null,
        ]);
        return response()->json([
            'status'    => 'success',
            'pinned_at' => null,
        ]);
    }

    /**
     * Mute or unmute a group conversation for the current user. Accepts until or minutes like DM mute.
     * POST /groups/{id}/mute
     */
    public function mute(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user  = $request->user();
        abort_unless($group->isMember($user), 403);

        $pivot = $group->members()->where('users.id', $user->id)->first()?->pivot;
        $mutedUntil = null;
        if ($request->filled('until')) {
            $mutedUntil = \Carbon\Carbon::parse($request->input('until'));
        } elseif ($request->filled('minutes')) {
            $minutes = max((int) $request->input('minutes'), 1);
            $mutedUntil = now()->addMinutes($minutes);
        } else {
            if ($pivot && $pivot->muted_until && $pivot->muted_until->isFuture()) {
                $mutedUntil = null;
            } else {
                $mutedUntil = now()->addYears(5);
            }
        }
        $group->members()->updateExistingPivot($user->id, [
            'muted_until' => $mutedUntil,
        ]);

        return response()->json([
            'status'      => 'success',
            'muted_until' => $mutedUntil ? $mutedUntil->toIso8601String() : null,
        ]);
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
