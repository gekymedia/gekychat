<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\Status;
use App\Models\Conversation;

class ChannelController extends Controller
{
    protected $channels;
    
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display channels page
     */
    public function index()
    {
        $userId = Auth::id();
        $user = Auth::user();

        // Load only channels (groups with type='channel')
        $channels = $user->groups()
            ->where('type', 'channel')
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
            ->each(function ($channel) use ($userId) {
                // Calculate unread count using the model's method
                $channel->unread_count = $channel->getUnreadCountForUser($userId);
            });

        // Load conversations (for sidebar)
        $conversations = $user->conversations()
            ->with([
                'members',
                'lastMessage',
            ])
            ->withMax('messages', 'created_at')
            ->orderByDesc('conversation_user.pinned_at')
            ->orderByDesc('messages_max_created_at')
            ->get()
            ->each(function ($conversation) use ($userId) {
                $conversation->unread_count = $conversation->unreadCountFor($userId);
            });

        // Load regular groups (for sidebar, excluding channels)
        $groups = $user->groups()
            ->where('type', '!=', 'channel')
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

        // Build forward datasets (same as ChatController)
        [$forwardDMs, $forwardGroups] = $this->buildForwardDatasets($userId, $conversations, $groups);

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

        return view('channels.index', compact('channels', 'conversations', 'groups', 'forwardDMs', 'forwardGroups', 'statuses', 'userIds'));
    }

    /**
     * Build forward datasets (same as ChatController)
     */
    private function buildForwardDatasets($userId, $conversations, $groups)
    {
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
        
        // Add channels to forward groups (if available)
        if (isset($this->channels) && $this->channels) {
            $forwardChannels = $this->channels->map(function ($channel) {
                return [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'avatar' => $channel->avatar_url ?? null,
                ];
            });
            
            $forwardGroups = $forwardGroups->merge($forwardChannels);
        }

        return [$forwardDMs, $forwardGroups];
    }
}
