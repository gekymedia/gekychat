<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\CallSession;
use App\Models\User;
use App\Support\CallLogPresenter;

class CallLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display call logs page
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $calls = CallLogPresenter::queryForUser($user)->paginate(20);

        $calls->getCollection()->transform(function ($call) use ($user) {
            $call->duration = CallLogPresenter::durationSeconds($call);
            $call->is_missed = CallLogPresenter::isMissed($call, $user, $call->duration);
            $call->is_outgoing = (int) $call->caller_id === (int) $user->id;

            if ($call->group_id && $call->group) {
                $call->other_user = null;
                $call->group_display = $call->group;
            } else {
                $other = $call->is_outgoing ? $call->callee : $call->caller;
                $call->other_user = $other;
                $call->group_display = null;
            }

            return $call;
        });
        
        // Return JSON for AJAX requests (Load More functionality)
        if ($request->ajax() || $request->wantsJson()) {
            $html = '';
            foreach ($calls as $call) {
                $html .= view('calls.partials.call-item', compact('call'))->render();
            }
            
            return response()->json([
                'html' => $html,
                'hasMore' => $calls->hasMorePages(),
                'currentPage' => $calls->currentPage(),
                'lastPage' => $calls->lastPage(),
                'total' => $calls->total(),
            ]);
        }
        
        // Load sidebar data (same structure as ChatController)
        $userId = Auth::id();
        
        // Load conversations
        $conversations = $user->conversations()
            ->with([
                'members:id,name,phone,avatar_path',
                'lastMessage',
            ])
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->get();

        // Load groups
        $groups = $user->groups()
            ->with([
                'members:id',
                'messages' => function ($q) {
                    $q->latest()->limit(1);
                },
                'messages.sender:id,name,avatar_path',
            ])
            ->withCount(['members as unread_count' => function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->where('last_read_message_id', '<', \DB::raw('(SELECT COALESCE(MAX(id), 0) FROM group_messages WHERE group_messages.group_id = group_members.group_id)'));
            }])
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->get();

        // Get user IDs for statuses
        $userIds = $user->contacts()
            ->whereNotNull('contact_user_id')
            ->pluck('contact_user_id')
            ->toArray();
        $userIds[] = $userId; // Include self
        
        return view('calls.index', compact('calls', 'user', 'conversations', 'groups', 'userIds'));
    }
}

