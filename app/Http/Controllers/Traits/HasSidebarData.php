<?php

namespace App\Http\Controllers\Traits;

use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\Status;
use App\Models\BroadcastList;
use Illuminate\Support\Facades\Auth;

trait HasSidebarData
{
    protected function getSidebarData(): array
    {
        $userId = Auth::id();
        $user = Auth::user();

        // Load conversations
        $conversations = $user->conversations()
            ->with([
                'members', 
                'lastMessage',
                'drafts' => function ($q) use ($userId) {
                    $q->where('user_id', $userId)->latest('saved_at');
                }
            ])
            ->withMax('messages', 'created_at')
            ->orderByDesc('conversation_user.pinned_at')
            ->orderByDesc('messages_max_created_at')
            ->get()
            ->each(function ($conversation) use ($userId) {
                $conversation->unread_count = $conversation->unreadCountFor($userId);
            });

        // Load groups
        $groups = $user->groups()
            ->with([
                'members',
                'messages' => function ($q) {
                    $q->with('sender')->latest()->limit(1);
                },
            ])
            ->orderByDesc(
                GroupMessage::select('created_at')
                    ->whereColumn('group_messages.group_id', 'groups.id')
                    ->latest()
                    ->take(1)
            )
            ->get()
            ->each(function ($group) use ($userId) {
                $group->unread_count = $group->getUnreadCountForUser($userId);
            });

        // Load broadcast lists
        $broadcastLists = $user->broadcastLists()
            ->with('recipients')
            ->orderByDesc('updated_at')
            ->get()
            ->each(function ($broadcastList) {
                $broadcastList->unread_count = 0; // Broadcast lists don't have unread messages
            });

        // Build forward datasets
        $forwardDMs = $conversations->map(function ($conv) use ($userId) {
            $other = $conv->members->where('id', '!=', $userId)->first();
            return [
                'id' => $conv->id,
                'name' => $other?->name ?? $other?->phone ?? 'Unknown',
                'avatar' => $other?->avatar_url ?? null,
            ];
        });

        $forwardGroups = $groups->map(function ($group) {
            return [
                'id' => $group->id,
                'name' => $group->name,
                'avatar' => $group->avatar_url ?? null,
            ];
        });

        // Get active statuses for sidebar
        $otherStatuses = Status::with(['user'])
            ->notExpired()
            ->visibleTo($userId)
            ->where('user_id', '!=', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('user_id')
            ->map(function ($userStatuses) use ($userId) {
                $status = $userStatuses->first();
                $status->is_unread = $userStatuses->contains(function ($s) use ($userId) {
                    return !$s->views()->where('user_id', $userId)->exists();
                });
                return $status;
            })
            ->values();

        // Get own status
        $ownStatus = Status::with(['user'])
            ->notExpired()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();

        // Merge own status with other statuses
        $statuses = collect();
        if ($ownStatus) {
            $ownStatus->is_unread = false;
            $statuses->push($ownStatus);
        }
        $statuses = $statuses->merge($otherStatuses);

        // Get user IDs for statuses
        $userIds = $user->contacts()
            ->whereNotNull('contact_user_id')
            ->pluck('contact_user_id')
            ->toArray();
        $userIds[] = $userId;

        // Check for bot conversation
        $botConversation = null;
        $botUser = \App\Models\User::where('phone', config('app.bot_phone', '+2330000000000'))->first();
        if ($botUser) {
            $botConversation = $user->conversations()
                ->whereHas('members', function ($q) use ($botUser) {
                    $q->where('users.id', $botUser->id);
                })
                ->with(['members', 'lastMessage'])
                ->first();
            if ($botConversation) {
                $botConversation->unread_count = $botConversation->unreadCountFor($userId);
            }
        }

        return compact('conversations', 'groups', 'broadcastLists', 'forwardDMs', 'forwardGroups', 'statuses', 'userIds', 'botConversation');
    }
}

