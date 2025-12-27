<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\CallSession;
use App\Models\User;

class CallLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display call logs page
     */
    public function index()
    {
        $user = Auth::user();
        
        // Get all calls where user is either caller or callee
        $calls = CallSession::where(function($query) use ($user) {
                $query->where('caller_id', $user->id)
                      ->orWhere('callee_id', $user->id);
            })
            ->whereNull('group_id') // Only direct calls for now
            ->with(['caller:id,name,phone,avatar_path', 'callee:id,name,phone,avatar_path'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        // Calculate duration for each call
        $calls->getCollection()->transform(function($call) use ($user) {
            $call->duration = null;
            if ($call->started_at && $call->ended_at) {
                $call->duration = $call->started_at->diffInSeconds($call->ended_at);
            }
            
            // Determine if call was missed (not answered or very short)
            $call->is_missed = !$call->started_at || ($call->duration !== null && $call->duration < 2);
            
            // Determine if it's outgoing or incoming
            $call->is_outgoing = $call->caller_id === $user->id;
            $call->other_user = $call->is_outgoing ? $call->callee : $call->caller;
            
            return $call;
        });
        
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

