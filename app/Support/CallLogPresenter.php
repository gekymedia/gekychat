<?php

namespace App\Support;

use App\Models\CallSession;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds call history for a user (1:1, group, and meeting sessions).
 */
class CallLogPresenter
{
    /**
     * @return Builder<CallSession>
     */
    public static function queryForUser(User $user): Builder
    {
        return CallSession::query()
            ->where(function (Builder $query) use ($user) {
                $query->where('caller_id', $user->id)
                    ->orWhere('callee_id', $user->id)
                    ->orWhereHas('participants', function (Builder $q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
            })
            ->with([
                'caller:id,name,phone,avatar_path',
                'callee:id,name,phone,avatar_path',
                'group:id,name,avatar_path',
                'participants' => function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                },
            ])
            ->orderByDesc('created_at');
    }

    /**
     * @param  Collection<int, Contact>|null  $contacts  keyed by contact_user_id
     * @return array<string, mixed>
     */
    public static function toApiArray(CallSession $call, User $user, ?Collection $contacts = null): array
    {
        $duration = self::durationSeconds($call);
        $isOutgoing = (int) $call->caller_id === (int) $user->id;
        $isMissed = self::isMissed($call, $user, $duration);
        $isGroupLike = $call->group_id !== null || $call->is_meeting;

        $groupData = null;
        if ($call->group_id && $call->group) {
            $groupData = [
                'id' => $call->group->id,
                'name' => $call->group->name,
                'avatar_url' => $call->group->avatar_path
                    ? asset('storage/'.$call->group->avatar_path)
                    : null,
            ];
        }

        $otherUserData = $isGroupLike
            ? null
            : self::resolveOtherUser($call, $user, $contacts);

        return [
            'id' => $call->id,
            'type' => $call->type,
            'duration' => $duration,
            'is_missed' => $isMissed,
            'is_outgoing' => $isOutgoing,
            'caller_id' => $call->caller_id,
            'callee_id' => $call->callee_id,
            'conversation_id' => $call->conversation_id,
            'group_id' => $call->group_id,
            'is_meeting' => (bool) $call->is_meeting,
            'other_user' => $otherUserData,
            'group' => $groupData,
            'started_at' => $call->started_at?->toIso8601String(),
            'ended_at' => $call->ended_at?->toIso8601String(),
            'created_at' => $call->created_at->toIso8601String(),
        ];
    }

    public static function durationSeconds(CallSession $call): ?int
    {
        if (! $call->started_at || ! $call->ended_at) {
            return null;
        }

        return (int) round($call->started_at->diffInSeconds($call->ended_at));
    }

    public static function isMissed(CallSession $call, User $user, ?int $duration): bool
    {
        if ($call->group_id || $call->is_meeting) {
            $participant = $call->participants->first();
            if ($participant && in_array($participant->status, ['invited', 'declined'], true)) {
                return true;
            }
        }

        return ! $call->started_at || ($duration !== null && $duration < 2);
    }

    /**
     * @param  Collection<int, Contact>|null  $contacts
     * @return array<string, mixed>|null
     */
    protected static function resolveOtherUser(
        CallSession $call,
        User $user,
        ?Collection $contacts = null,
    ): ?array {
        $isOutgoing = (int) $call->caller_id === (int) $user->id;
        $otherUser = $isOutgoing ? $call->callee : $call->caller;
        $otherUserId = $isOutgoing ? $call->callee_id : $call->caller_id;

        if (! $otherUser && $otherUserId) {
            $otherUser = User::find($otherUserId);
        }

        if (! $otherUser && ! $otherUserId) {
            return null;
        }

        $contact = ($contacts && $otherUserId) ? $contacts->get($otherUserId) : null;

        if ($otherUser) {
            $phone = $otherUser->phone;
            if (empty($phone) && $contact && ! empty($contact->phone)) {
                $phone = $contact->phone;
            }

            $name = $otherUser->name;
            if ($contact && ! empty($contact->display_name)) {
                $name = $contact->display_name;
            } elseif (empty($name) || $name === 'Unknown') {
                $name = $phone ?? 'User '.$otherUser->id;
            }

            return [
                'id' => $otherUser->id,
                'name' => $name,
                'phone' => $phone,
                'avatar_url' => $otherUser->avatar_path
                    ? asset('storage/'.$otherUser->avatar_path)
                    : null,
            ];
        }

        $phone = $contact ? $contact->phone : null;
        $name = $contact && ! empty($contact->display_name)
            ? $contact->display_name
            : ($phone ?? 'User '.$otherUserId);

        return [
            'id' => $otherUserId,
            'name' => $name,
            'phone' => $phone,
            'avatar_url' => null,
        ];
    }
}
