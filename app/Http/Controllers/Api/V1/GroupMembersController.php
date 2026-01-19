<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

class GroupMembersController extends Controller
{
    /**
     * POST /api/v1/groups/{group}/members/phones
     * Body: { "phones": ["0551234567", "+233551234567", ...] }
     * Auth: sanctum
     */
    public function addByPhones(Request $request, Group $group)
    {
        Gate::authorize('manage-group', $group);

        $data = $request->validate([
            'phones'   => ['required', 'array', 'min:1'],
            'phones.*' => ['string', 'max:32'],
        ]);

        $phones = collect($data['phones'])
            ->map(fn ($p) => trim($p))
            ->filter()
            ->unique()
            ->values();

        if ($phones->isEmpty()) {
            return response()->json([
                'status' => 'ok',
                'group_id' => $group->id,
                'added_user_ids' => [],
                'already_members' => [],
                'not_found' => [],
            ]);
        }

        // Same tolerant matching approach as the resolver
        $last9Set = $phones
            ->map(fn ($p) => preg_replace('/\D+/', '', $p))
            ->map(fn ($digits) => strlen($digits) >= 9 ? substr($digits, -9) : $digits)
            ->filter()
            ->unique()
            ->values();

        $users = User::query()
            ->whereIn('phone', $phones)
            ->orWhere(function ($q) use ($last9Set) {
                foreach ($last9Set as $last9) {
                    $q->orWhereRaw('RIGHT(REGEXP_REPLACE(phone, "[^0-9]", ""), 9) = ?', [$last9]);
                }
            })
            ->get(['id', 'phone']);

        // Index by normalized last-9 for a quick reverse lookup
        $byLast9 = $users->keyBy(function ($u) {
            $digits = preg_replace('/\D+/', '', $u->phone ?? '');
            return strlen($digits) >= 9 ? substr($digits, -9) : $digits;
        });

        $attach = [];
        $added = [];
        $already = [];
        $notFound = [];

        // Pull existing member ids
        $existingIds = $group->members()->pluck('users.id')->all();
        $existingMap = array_flip($existingIds);

        foreach ($phones as $p) {
            $digits = preg_replace('/\D+/', '', $p);
            $last9  = strlen($digits) >= 9 ? substr($digits, -9) : $digits;

            $user = $users->firstWhere('phone', $p) ?: ($byLast9[$last9] ?? null);

            if (!$user) {
                $notFound[] = $p;
                continue;
            }

            if (isset($existingMap[$user->id])) {
                $already[] = $user->id;
                continue;
            }

            $attach[$user->id] = ['role' => 'member', 'joined_at' => now()];
            $added[] = $user->id;
        }

        if (!empty($attach)) {
            DB::transaction(function () use ($group, $attach) {
                $group->members()->syncWithoutDetaching($attach);
                
                // Create system messages for each new member
                foreach ($attach as $userId => $data) {
                    $group->createSystemMessage('joined', $userId);
                }
            });
        }

        return response()->json([
            'status'          => 'success',
            'group_id'        => $group->id,
            'added_user_ids'  => array_values(array_unique($added)),
            'already_members' => array_values(array_unique($already)),
            'not_found'       => array_values(array_unique($notFound)),
        ]);
    }

    /**
     * DELETE /api/v1/groups/{id}/members/{userId}
     * Remove a member from the group. Only group owners or admins may perform this action.
     */
    public function remove(Request $request, $id, $userId)
    {
        $group = Group::findOrFail($id);
        $user = User::findOrFail($userId);
        
        Gate::authorize('manage-group', $group);

        // Owners cannot remove themselves
        if ($group->owner_id === $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'The group owner cannot be removed.',
            ], 422);
        }

        $group->members()->detach($user->id);
        
        // Create system message when user is removed
        $group->createSystemMessage('removed', $user->id);

        return response()->json([
            'status'   => 'success',
            'group_id' => $group->id,
            'removed_user_id' => $user->id,
        ]);
    }

    /**
     * POST /api/v1/groups/{id}/members/{userId}/promote
     * Promote a member to an admin. Only the group owner may promote.
     */
    public function promote(Request $request, $id, $userId)
    {
        $group = Group::findOrFail($id);
        $user = User::findOrFail($userId);
        
        Gate::authorize('manage-group', $group);
        // Only owner can promote
        if ($group->owner_id !== $request->user()->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only the group owner can promote members.',
            ], 403);
        }
        $group->members()->updateExistingPivot($user->id, [
            'role' => 'admin',
        ]);
        
        // Create system message when user is promoted
        $group->createSystemMessage('promoted', $user->id);
        
        return response()->json([
            'status'   => 'success',
            'group_id' => $group->id,
            'user_id'  => $user->id,
            'role'     => 'admin',
        ]);
    }

    /**
     * POST /api/v1/groups/{id}/members/{userId}/demote
     * Demote an admin back to a regular member. Only the group owner may demote.
     */
    public function demote(Request $request, $id, $userId)
    {
        $group = Group::findOrFail($id);
        $user = User::findOrFail($userId);
        
        Gate::authorize('manage-group', $group);
        // Only owner can demote and cannot demote themselves
        if ($group->owner_id !== $request->user()->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only the group owner can demote members.',
            ], 403);
        }
        if ($group->owner_id === $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Owner cannot be demoted.',
            ], 422);
        }
        $group->members()->updateExistingPivot($user->id, [
            'role' => 'member',
        ]);
        
        // Create system message when user is demoted
        $group->createSystemMessage('demoted', $user->id);
        
        return response()->json([
            'status'   => 'success',
            'group_id' => $group->id,
            'user_id'  => $user->id,
            'role'     => 'member',
        ]);
    }
}
