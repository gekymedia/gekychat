<?php

namespace App\Http\View\Composers;

use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\Status;

class SidebarComposer
{
    /**
     * Bind data to the view.
     * This ensures sidebar data is ALWAYS available on every page.
     */
    public function compose(View $view): void
    {
        // Only load if user is authenticated
        if (!Auth::check()) {
            $view->with([
                'conversations' => collect(),
                'groups' => collect(),
                'botConversation' => null,
                'forwardDMs' => [],
                'forwardGroups' => [],
                'statuses' => collect(),
            ]);
            return;
        }

        $user = Auth::user();
        $userId = $user->id;

        try {
            // Load conversations with unread count
            $conversations = $user->conversations()
                ->with([
                    'members:id,name,phone,avatar_path',
                    'lastMessage',
                ])
                ->withMax('messages', 'created_at')
                ->get()
                // Sort: pinned first, then by latest message
                ->sortByDesc(function ($conv) {
                    $pinnedAt = $conv->pivot->pinned_at ?? null;
                    $maxCreatedAt = $conv->messages_max_created_at ?? null;
                    
                    // Convert pinned_at to Carbon if it's a string
                    if ($pinnedAt) {
                        $pinnedAt = is_string($pinnedAt) 
                            ? \Carbon\Carbon::parse($pinnedAt) 
                            : $pinnedAt;
                        return $pinnedAt->timestamp + 9999999999;
                    }
                    
                    // Convert messages_max_created_at to Carbon if it's a string
                    if ($maxCreatedAt) {
                        $maxCreatedAt = is_string($maxCreatedAt)
                            ? \Carbon\Carbon::parse($maxCreatedAt)
                            : $maxCreatedAt;
                        return $maxCreatedAt->timestamp ?? 0;
                    }
                    
                    return 0;
                })
                ->values()
                ->each(function ($conversation) use ($userId) {
                    // Calculate unread count
                    $conversation->unread_count = $conversation->messages()
                        ->where('sender_id', '!=', $userId)
                        ->whereDoesntHave('statuses', function ($q) use ($userId) {
                            $q->where('user_id', $userId)
                              ->where('status', 'read');
                        })
                        ->count();
                });

            // Load groups with unread count
            $groups = $user->groups()
                ->with([
                    'members:id,name,phone,avatar_path',
                    'messages' => function ($q) {
                        $q->with('sender:id,name,phone,avatar_path')
                          ->latest()
                          ->limit(1);
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
                    // Calculate unread count for groups
                    $lastRead = $group->members()
                        ->where('users.id', $userId)
                        ->first()?->pivot->last_read_at;

                    if ($lastRead) {
                        $group->unread_count = $group->messages()
                            ->where('created_at', '>', $lastRead)
                            ->where('sender_id', '!=', $userId)
                            ->count();
                    } else {
                        $group->unread_count = $group->messages()
                            ->where('sender_id', '!=', $userId)
                            ->count();
                    }
                });

            // Load GekyBot conversation if it exists
            $botConversation = $conversations->firstWhere('slug', 'gekybot') 
                ?? $conversations->firstWhere('is_geky_bot', true);

            // Build forward datasets for message forwarding
            $forwardDMs = $conversations->map(function ($c) use ($userId) {
                return [
                    'id'       => $c->id,
                    'slug'     => $c->slug,
                    'title'    => $c->title,
                    'subtitle' => $c->is_saved_messages ? 'Saved Messages' : ($c->other_user?->phone ?? null),
                    'avatar'   => $c->other_user?->avatar_path ?? null,
                    'unread'   => $c->unread_count ?? 0,
                ];
            })->values()->toArray();

            $forwardGroups = $groups->map(function ($g) use ($userId) {
                // Get user's role in the group
                $member = $g->members()->where('user_id', $userId)->first();
                $userRole = $member?->pivot->role ?? 'member';
                $isAdmin = $userRole === 'admin' || $g->owner_id === $userId;
                
                return [
                    'id'       => $g->id,
                    'slug'     => $g->slug,
                    'title'    => $g->name ?? 'Group',
                    'subtitle' => $g->type === 'channel' ? 'Public Channel' : 'Private Group',
                    'avatar'   => $g->avatar_path ?? null,
                    'unread'   => $g->unread_count ?? 0,
                    'user_role' => $userRole,
                    'is_admin' => $isAdmin,
                ];
            })->values()->toArray();

            // Load active statuses for sidebar
            $otherStatuses = Status::with(['user', 'views' => function ($q) use ($userId) {
                $q->where('user_id', $userId);
            }])
                ->notExpired()
                ->visibleTo($userId)
                ->where('user_id', '!=', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Get contact display names for status users
            $contactDisplayNames = \App\Models\Contact::where('user_id', $userId)
                ->whereNotNull('contact_user_id')
                ->get()
                ->keyBy('contact_user_id')
                ->map(function ($contact) {
                    return $contact->display_name;
                });

            // Get user's own statuses (include all non-expired)
            $myStatuses = Status::where('user_id', $userId)
                ->notExpired()
                ->orderBy('created_at', 'desc')
                ->get();

            // Group statuses by user_id
            $groupedStatuses = collect();
            
            // Group own statuses
            if ($myStatuses->isNotEmpty()) {
                $groupedStatuses->push((object)[
                    'user_id' => $userId,
                    'user' => $user,
                    'display_name' => $user->name ?? 'Me',
                    'statuses' => $myStatuses,
                    'status_count' => $myStatuses->count(),
                    'is_unread' => false, // Own statuses are always considered viewed
                    'latest_status' => $myStatuses->first(),
                ]);
            }

            // Group other users' statuses
            $otherStatusesGrouped = $otherStatuses->groupBy('user_id');
            foreach ($otherStatusesGrouped as $statusUserId => $userStatuses) {
                $statusUser = $userStatuses->first()->user;
                $displayName = isset($contactDisplayNames[$statusUserId]) 
                    ? $contactDisplayNames[$statusUserId]
                    : ($statusUser->name ?? $statusUser->phone ?? 'User');
                
                // Check if any status from this user is unread
                $hasUnread = $userStatuses->contains(function ($status) use ($userId) {
                    return !$status->views || $status->views->isEmpty();
                });

                $groupedStatuses->push((object)[
                    'user_id' => $statusUserId,
                    'user' => $statusUser,
                    'display_name' => $displayName,
                    'statuses' => $userStatuses,
                    'status_count' => $userStatuses->count(),
                    'is_unread' => $hasUnread,
                    'latest_status' => $userStatuses->first(),
                ]);
            }

            // Sort by latest status creation time (most recent first)
            $statuses = $groupedStatuses->sortByDesc(function ($group) {
                return $group->latest_status->created_at ?? now();
            })->values();

            // Share with view
            $view->with(compact(
                'conversations',
                'groups',
                'botConversation',
                'forwardDMs',
                'forwardGroups',
                'statuses'
            ));

        } catch (\Throwable $e) {
            // Log error and provide empty defaults
            \Log::error('SidebarComposer failed to load data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $view->with([
                'conversations' => collect(),
                'groups' => collect(),
                'botConversation' => null,
                'forwardDMs' => [],
                'forwardGroups' => [],
                'statuses' => collect(),
            ]);
        }
    }
}
