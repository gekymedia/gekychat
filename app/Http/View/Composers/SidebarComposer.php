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
                    return $conv->pivot->pinned_at 
                        ? $conv->pivot->pinned_at->timestamp + 9999999999
                        : $conv->messages_max_created_at?->timestamp ?? 0;
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
                            ->where('user_id', '!=', $userId)
                            ->count();
                    } else {
                        $group->unread_count = $group->messages()
                            ->where('user_id', '!=', $userId)
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

            $forwardGroups = $groups->map(function ($g) {
                return [
                    'id'       => $g->id,
                    'slug'     => $g->slug,
                    'title'    => $g->name ?? 'Group',
                    'subtitle' => $g->type === 'channel' ? 'Public Channel' : 'Private Group',
                    'avatar'   => $g->avatar_path ?? null,
                    'unread'   => $g->unread_count ?? 0,
                ];
            })->values()->toArray();

            // Load active statuses for sidebar
            $otherStatuses = Status::with(['user'])
                ->notExpired()
                ->visibleTo($userId)
                ->where('user_id', '!=', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Get user's own status if it exists
            $myStatus = Status::where('user_id', $userId)
                ->notExpired()
                ->orderBy('created_at', 'desc')
                ->first();

            $statuses = collect();
            if ($myStatus) {
                $statuses->push($myStatus);
            }
            $statuses = $statuses->merge($otherStatuses);

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
