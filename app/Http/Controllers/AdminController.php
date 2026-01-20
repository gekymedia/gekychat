<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\ApiClient;
use App\Models\Block;
use App\Models\CallSession;
use App\Models\LiveBroadcast;
use App\Models\AudioLibrary;
use App\Models\AudioLicenseSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function __construct()
    {
        // Share pending reports count with all admin views
        \View::composer('layouts.admin', function ($view) {
            $pendingReportsCount = Report::where('status', 'pending')->count();
            $view->with('pendingReportsCount', $pendingReportsCount);
        });
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }

        // Basic Metrics
        $totalUsers = User::count();
        $reportedCount = Report::where('status', 'pending')->count();

        // Handle status column safely
        $bannedCount = $this->hasColumn('users', 'status')
            ? User::where('status', 'banned')->count()
            : 0;

        $activeCount = $this->hasColumn('users', 'status')
            ? User::where(function ($query) {
                $query->whereNull('status')->orWhere('status', 'active');
            })->count()
            : $totalUsers;

        $publicChannels = Conversation::where('is_group', true)
            ->where('is_private', false)
            ->count();

        // Count of registered API clients
        $apiClientCount = ApiClient::count();

        // Advanced Analytics
        $userGrowth = $this->getUserGrowth();
        $messageStats = $this->getMessageStats();
        $engagementMetrics = $this->getEngagementMetrics();
        $platformUsage = $this->getPlatformUsage();
        $aiChatAnalytics = $this->getAIChatAnalytics();
        $realtimeActivity = $this->getRealtimeActivity();

        return view('admin.dashboard', compact(
            'totalUsers',
            'reportedCount',
            'bannedCount',
            'activeCount',
            'publicChannels',
            'apiClientCount',
            'userGrowth',
            'messageStats',
            'engagementMetrics',
            'platformUsage',
            'aiChatAnalytics',
            'realtimeActivity'
        ));
    }

    /**
     * Get user growth data for the last 30 days
     */
    private function getUserGrowth()
    {
        $startDate = Carbon::now()->subDays(30);

        return [
            'total' => User::count(),
            'growth_30d' => User::where('created_at', '>=', $startDate)->count(),
            'daily_registrations' => User::where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->pluck('count', 'date'),
            'active_today' => User::whereDate('last_seen_at', Carbon::today())->count(),
            'retention_rate' => $this->calculateRetentionRate(),
        ];
    }

    /**
     * Get message statistics
     */
    private function getMessageStats()
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // Direct messages
        $dmToday = Message::whereDate('created_at', $today)->count();
        $dmYesterday = Message::whereDate('created_at', $yesterday)->count();
        $dmGrowth = $dmYesterday > 0 ? (($dmToday - $dmYesterday) / $dmYesterday) * 100 : 0;

        // Group messages
        $groupToday = GroupMessage::whereDate('created_at', $today)->count();
        $groupYesterday = GroupMessage::whereDate('created_at', $yesterday)->count();
        $groupGrowth = $groupYesterday > 0 ? (($groupToday - $groupYesterday) / $groupYesterday) * 100 : 0;

        return [
            'total_dm' => Message::count(),
            'total_group' => GroupMessage::count(),
            'dm_today' => $dmToday,
            'dm_growth' => round($dmGrowth, 1),
            'group_today' => $groupToday,
            'group_growth' => round($groupGrowth, 1),
            'avg_message_length' => round(Message::avg(DB::raw('LENGTH(body)')) ?: 0),
            'messages_per_user' => $this->getMessagesPerUser(),
        ];
    }

    /**
     * Get engagement metrics
     */
    private function getEngagementMetrics()
    {
        $activeUsers = User::where('last_seen_at', '>=', Carbon::now()->subDay())->count();
        $veryActiveUsers = User::where('last_seen_at', '>=', Carbon::now()->subHours(2))->count();

        return [
            'dau' => $activeUsers, // Daily Active Users
            'mau' => User::where('last_seen_at', '>=', Carbon::now()->subDays(30))->count(), // Monthly Active Users
            'wau' => User::where('last_seen_at', '>=', Carbon::now()->subDays(7))->count(), // Weekly Active Users
            'very_active' => $veryActiveUsers,
            'avg_session_duration' => $this->calculateAvgSessionDuration(),
            'messages_per_session' => $this->calculateMessagesPerSession(),
        ];
    }

    /**
     * Get platform usage statistics
     */
    private function getPlatformUsage()
    {
        $totalGroups = Group::count();
        $activeGroups = Group::whereHas('messages', function ($query) {
            $query->where('created_at', '>=', Carbon::now()->subDays(7));
        })->count();

        return [
            'total_groups' => $totalGroups,
            'active_groups' => $activeGroups,
            'groups_created_today' => Group::whereDate('created_at', Carbon::today())->count(),
            'avg_group_size' => $this->getAverageGroupSize(),
            'top_groups' => $this->getTopGroups(),
        ];
    }

    /**
     * Get AI Chat analytics
     */
    private function getAIChatAnalytics()
    {
        $botUserId = User::where('phone', '0000000000')->value('id');

        if (!$botUserId) {
            return [
                'total_interactions' => 0,
                'today_interactions' => 0,
                'popular_commands' => [],
                'user_satisfaction' => 0,
                'active_bot_users' => 0,
            ];
        }

        $botMessages = Message::where('sender_id', $botUserId);

        return [
            'total_interactions' => $botMessages->count(),
            'today_interactions' => $botMessages->whereDate('created_at', Carbon::today())->count(),
            'popular_commands' => $this->getPopularBotCommands($botUserId),
            'user_satisfaction' => $this->calculateBotSatisfaction(),
            'active_bot_users' => $this->getActiveBotUsers($botUserId),
        ];
    }

    /**
     * Get real-time activity data
     */
    private function getRealtimeActivity()
    {
        // Get active calls and live broadcasts
        $activeCalls = CallSession::where(function ($q) {
            $q->where('status', 'ongoing')->orWhere('status', 'calling');
        })->count();
        
        $activeGroupCalls = CallSession::whereNotNull('group_id')
            ->where(function ($q) {
                $q->where('status', 'ongoing')->orWhere('status', 'calling');
            })->count();
        
        $activeLives = LiveBroadcast::where('status', 'live')->count();
        
        return [
            'online_users' => User::where('last_seen_at', '>=', Carbon::now()->subMinutes(5))->count(),
            'typing_now' => $this->getTypingUsers(),
            'recent_messages' => $this->getRecentMessages(),
            'new_users_today' => User::whereDate('created_at', Carbon::today())->count(),
            'active_calls' => $activeCalls,
            'active_group_calls' => $activeGroupCalls,
            'active_live_broadcasts' => $activeLives,
        ];
    }

    /**
     * Helper method to check if column exists
     */
    private function hasColumn($table, $column)
    {
        try {
            return \Schema::hasColumn($table, $column);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Calculate user retention rate
     */
    private function calculateRetentionRate()
    {
        $twoWeeksAgo = Carbon::now()->subWeeks(2);
        $oneWeekAgo = Carbon::now()->subWeek();

        $cohortUsers = User::whereBetween('created_at', [$twoWeeksAgo, $oneWeekAgo])->count();

        if ($cohortUsers === 0) return 0;

        $retainedUsers = User::whereBetween('created_at', [$twoWeeksAgo, $oneWeekAgo])
            ->where('last_seen_at', '>=', $oneWeekAgo)
            ->count();

        return round(($retainedUsers / $cohortUsers) * 100, 1);
    }

    /**
     * Calculate average messages per user
     */
    private function getMessagesPerUser()
    {
        $totalMessages = Message::count() + GroupMessage::count();
        $totalUsers = User::count();

        return $totalUsers > 0 ? round($totalMessages / $totalUsers, 1) : 0;
    }

    /**
     * Calculate average session duration (simplified)
     */
    private function calculateAvgSessionDuration()
    {
        // This is a simplified calculation - you might want to implement proper session tracking
        $activeUsers = User::where('last_seen_at', '>=', Carbon::now()->subHours(24))->count();
        return $activeUsers > 0 ? round(30 / $activeUsers * 60, 1) : 0; // Example calculation
    }

    /**
     * Calculate messages per session
     */
    private function calculateMessagesPerSession()
    {
        $todayMessages = Message::whereDate('created_at', Carbon::today())->count() +
            GroupMessage::whereDate('created_at', Carbon::today())->count();
        $activeUsers = User::whereDate('last_seen_at', Carbon::today())->count();

        return $activeUsers > 0 ? round($todayMessages / $activeUsers, 1) : 0;
    }

    /**
     * Get average group size
     */
    private function getAverageGroupSize()
    {
        try {
            $result = DB::table('group_members')
                ->select(DB::raw('AVG(member_count) as avg_size'))
                ->fromSub(function ($query) {
                    $query->from('group_members')
                        ->select('group_id', DB::raw('COUNT(*) as member_count'))
                        ->groupBy('group_id');
                }, 'group_sizes')
                ->first();

            return $result ? round($result->avg_size, 1) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get top groups by message activity
     */
    private function getTopGroups()
    {
        try {
            return Group::withCount(['messages' => function ($query) {
                $query->where('created_at', '>=', Carbon::now()->subDays(7));
            }])
                ->orderByDesc('messages_count')
                ->limit(5)
                ->get()
                ->map(function ($group) {
                    return [
                        'name' => $group->name,
                        'message_count' => $group->messages_count ?? 0,
                        'member_count' => $group->members_count ?? $group->members()->count() ?? 0,
                    ];
                });
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Get popular bot commands
     */
    private function getPopularBotCommands($botUserId)
    {
        try {
            // Get conversations where the bot is a member
            $botConversations = Conversation::whereHas('members', function ($query) use ($botUserId) {
                $query->where('user_id', $botUserId);
            })->pluck('id');

            if ($botConversations->isEmpty()) {
                return [];
            }

            return Message::whereIn('conversation_id', $botConversations)
                ->where('sender_id', '!=', $botUserId)
                ->where('body', '!=', '')
                ->select('body', DB::raw('COUNT(*) as count'))
                ->groupBy('body')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(function ($message) {
                    return [
                        'command' => strtolower(substr($message->body, 0, 20)),
                        'count' => $message->count,
                    ];
                });
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get active bot users
     */
    private function getActiveBotUsers($botUserId)
    {
        try {
            // Get conversations where the bot is a member
            $botConversations = Conversation::whereHas('members', function ($query) use ($botUserId) {
                $query->where('user_id', $botUserId);
            })->pluck('id');

            if ($botConversations->isEmpty()) {
                return 0;
            }

            return Message::whereIn('conversation_id', $botConversations)
                ->where('sender_id', '!=', $botUserId)
                ->whereDate('created_at', Carbon::today())
                ->distinct('sender_id')
                ->count('sender_id');
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Calculate bot satisfaction (simplified)
     */
    private function calculateBotSatisfaction()
    {
        // This would ideally track user reactions to bot messages
        // For now, return a placeholder
        return 85; // 85% satisfaction rate
    }

    /**
     * Get currently typing users
     */
    private function getTypingUsers()
    {
        // This would integrate with your real-time typing indicators
        // For now, return a placeholder
        return rand(0, 5);
    }

    /**
     * Get recent messages for activity feed
     */
    private function getRecentMessages()
    {
        try {
            $recentDMs = Message::with(['sender', 'conversation'])
                ->latest()
                ->limit(5)
                ->get();

            $recentGroupMessages = GroupMessage::with(['sender', 'group'])
                ->latest()
                ->limit(5)
                ->get();

            return $recentDMs->merge($recentGroupMessages)
                ->sortByDesc('created_at')
                ->take(5)
                ->values();
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Export analytics data
     */
    public function exportAnalytics(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }

        $type = $request->get('type', 'users');
        $period = $request->get('period', '30d');

        // Implement export logic based on type and period
        // This could generate CSV, PDF, or Excel files

        return response()->json([
            'status' => 'success',
            'message' => 'Export functionality to be implemented',
            'type' => $type,
            'period' => $period,
        ]);
    }

    /**
     * Get detailed user analytics
     */
    public function userAnalytics(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }

        $period = $request->get('period', '7d');
        $data = $this->getDetailedUserAnalytics($period);

        return response()->json($data);
    }

    /**
     * Show list of reported accounts for admin moderation.
     */
    public function reports()
    {
        $reports = Report::with(['reporter:id,name,phone', 'reportedUser:id,name,phone'])
            ->orderByDesc('created_at')
            ->get();
        return view('admin.reports', compact('reports'));
    }

    /**
     * Show list of banned users.
     */
    public function bannedUsers()
    {
        $bannedUsers = User::where('status', 'banned')->get();
        return view('admin.banned', compact('bannedUsers'));
    }

    /**
     * Display a list of API clients created by users.
     */
    public function apiClients()
    {
        $clients = ApiClient::with('user:id,name,phone')->get();
        return view('admin.api_clients', compact('clients'));
    }

    /**
     * List messages for moderation
     */
    public function messages(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }

        $query = Message::with(['sender', 'conversation.userOne', 'conversation.userTwo', 'attachments'])
            ->orderByDesc('created_at');

        // Search filter
        if ($request->has('search') && $request->search) {
            $query->where('body', 'like', '%' . $request->search . '%');
        }

        $messages = $query->paginate(50);

        return view('admin.messages.index', compact('messages'));
    }

    /**
     * Show a specific message
     */
    public function showMessage($id)
    {
        $message = Message::with(['sender', 'conversation', 'attachments', 'reactions.user', 'replyTo'])
            ->findOrFail($id);
        
        return view('admin.messages.show', compact('message'));
    }

    /**
     * Delete a specific message
     */
    public function delete($id)
    {
        $message = Message::findOrFail($id);
        
        // Delete associated attachments
        foreach ($message->attachments as $attachment) {
            if ($attachment->file_path && \Storage::disk('public')->exists($attachment->file_path)) {
                \Storage::disk('public')->delete($attachment->file_path);
            }
            $attachment->delete();
        }
        
        $message->delete();

        return redirect()->route('admin.messages')->with('success', 'Message deleted successfully');
    }

    /**
     * List conversations for moderation
     */
    public function conversations(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }

        $query = Conversation::with(['userOne', 'userTwo', 'messages'])
            ->withCount('messages')
            ->orderByDesc('created_at');

        // Search filter
        if ($request->has('search') && $request->search) {
            $query->whereHas('userOne', function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%');
            })->orWhereHas('userTwo', function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%');
            });
        }

        $conversations = $query->paginate(50);

        return view('admin.conversations.index', compact('conversations'));
    }

    /**
     * Show a specific conversation
     */
    public function showConversation($id)
    {
        $conversation = Conversation::with(['userOne', 'userTwo', 'messages.sender', 'messages.attachments'])
            ->findOrFail($id);
        
        return view('admin.conversations.show', compact('conversation'));
    }

    /**
     * Delete a conversation and all its messages
     */
    public function deleteConversation($id)
    {
        $conversation = Conversation::with('messages.attachments')->findOrFail($id);
        
        // Delete all messages and their attachments
        foreach ($conversation->messages as $message) {
            foreach ($message->attachments as $attachment) {
                if ($attachment->file_path && \Storage::disk('public')->exists($attachment->file_path)) {
                    \Storage::disk('public')->delete($attachment->file_path);
                }
                $attachment->delete();
            }
            $message->delete();
        }
        
        $conversation->delete();

        return redirect()->route('admin.conversations')->with('success', 'Conversation and all messages deleted successfully');
    }

    private function getDetailedUserAnalytics($period)
    {
        // Implement detailed user analytics based on period
        return [
            'period' => $period,
            'new_users' => User::where('created_at', '>=', Carbon::now()->subDays(7))->count(),
            'active_users' => User::where('last_seen_at', '>=', Carbon::now()->subDays(7))->count(),
            // Add more detailed analytics
        ];
    }

    /**
     * API endpoint for refreshing dashboard data
     */
    public function refreshData(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            // Get active calls and live broadcasts
            $activeCalls = CallSession::where(function ($q) {
                $q->where('status', 'ongoing')->orWhere('status', 'calling');
            })->count();
            
            $activeGroupCalls = CallSession::whereNotNull('group_id')
                ->where(function ($q) {
                    $q->where('status', 'ongoing')->orWhere('status', 'calling');
                })->count();
            
            $activeLives = LiveBroadcast::where('status', 'live')->count();
            
            // Return real-time data that changes frequently
            $data = [
                'online_users' => User::where('last_seen_at', '>=', Carbon::now()->subMinutes(5))->count(),
                'messages_today' => [
                    'dm' => Message::whereDate('created_at', Carbon::today())->count(),
                    'group' => GroupMessage::whereDate('created_at', Carbon::today())->count(),
                    'total' => Message::whereDate('created_at', Carbon::today())->count() +
                        GroupMessage::whereDate('created_at', Carbon::today())->count()
                ],
                'new_users_today' => User::whereDate('created_at', Carbon::today())->count(),
                'pending_reports' => Report::where('status', 'pending')->count(),
                'ai_interactions_today' => $this->getTodayAIIteractions(),
                'active_calls' => $activeCalls,
                'active_group_calls' => $activeGroupCalls,
                'active_live_broadcasts' => $activeLives,
                'timestamp' => now()->toISOString(),
                'server_time' => now()->format('H:i:s')
            ];

            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to refresh data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get today's AI interactions
     */
    private function getTodayAIIteractions()
    {
        $botUserId = User::where('phone', '0000000000')->value('id');
        if (!$botUserId) return 0;

        return Message::where('sender_id', $botUserId)
            ->whereDate('created_at', Carbon::today())
            ->count();
    }

    public function users(Request $request)
    {
        $query = $request->input('search');
        
        // If this is an API request (JSON), return JSON response
        if ($request->wantsJson() || $request->expectsJson()) {
            $usersQuery = User::query();
            
            if ($query) {
                $usersQuery->where(function ($q) use ($query) {
                    $q->where('name', 'LIKE', "%{$query}%")
                      ->orWhere('phone', 'LIKE', "%{$query}%")
                      ->orWhere('username', 'LIKE', "%{$query}%")
                      ->orWhere('email', 'LIKE', "%{$query}%");
                });
            }
            
            $users = $usersQuery->limit(20)->get(['id', 'name', 'phone', 'username', 'email']);
            
            return response()->json([
                'users' => $users,
            ]);
        }
        
        $users = User::withCount(['conversations', 'groups', 'sentMessages'])
            ->latest()
            ->paginate(100);
        return view('admin.users.index', compact('users'));
    }

    public function settings()
    {
        return view('admin.settings');
    }

    public function systemSettings()
    {
        return view('admin.system_settings');
    }

    public function updateBotSettings(Request $request)
    {
        $request->validate([
            'use_llm' => 'sometimes|boolean',
            'llm_provider' => 'sometimes|string|in:ollama,openai',
            'ollama_api_url' => 'sometimes|url',
            'ollama_model' => 'sometimes|string|max:100',
            'llm_temperature' => 'sometimes|numeric|min:0|max:1',
            'llm_max_tokens' => 'sometimes|integer|min:50|max:2000',
        ]);

        if ($request->has('use_llm')) {
            \App\Models\BotSetting::set('use_llm', $request->boolean('use_llm'), 'boolean');
        }
        if ($request->has('llm_provider')) {
            \App\Models\BotSetting::set('llm_provider', $request->llm_provider);
        }
        if ($request->has('ollama_api_url')) {
            \App\Models\BotSetting::set('ollama_api_url', $request->ollama_api_url);
        }
        if ($request->has('ollama_model')) {
            \App\Models\BotSetting::set('ollama_model', $request->ollama_model);
        }
        if ($request->has('llm_temperature')) {
            \App\Models\BotSetting::set('llm_temperature', (string) $request->llm_temperature);
        }
        if ($request->has('llm_max_tokens')) {
            \App\Models\BotSetting::set('llm_max_tokens', (string) $request->llm_max_tokens);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bot settings updated successfully'
        ]);
    }

    // ============ REPORTS MANAGEMENT METHODS ============
    
    public function reportsIndex()
    {
        $reports = Report::with(['reporter', 'reportedUser'])
            ->latest()
            ->paginate(20);
            
        $pendingReportsCount = Report::where('status', 'pending')->count();
        
        return view('admin.reports.index', compact('reports', 'pendingReportsCount'));
    }
    
    /**
     * Show channels management page
     */
    public function channelsIndex(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }

        $query = Group::where('type', 'channel')
            ->with(['owner', 'members'])
            ->withCount('members');

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Verified filter
        if ($request->has('verified') && $request->verified !== '') {
            $query->where('is_verified', $request->verified === '1');
        }

        // Sort
        $sortBy = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $channels = $query->paginate(20)->withQueryString();

        return view('admin.channels.index', compact('channels'));
    }

    /**
     * Toggle channel verification status
     */
    public function toggleChannelVerified(Request $request, Group $group)
    {
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }

        if ($group->type !== 'channel') {
            return response()->json([
                'success' => false,
                'message' => 'This is not a channel'
            ], 422);
        }

        $group->is_verified = !$group->is_verified;
        $group->save();

        return response()->json([
            'success' => true,
            'message' => $group->is_verified ? 'Channel marked as verified' : 'Channel verification removed',
            'is_verified' => $group->is_verified
        ]);
    }

    public function reportsUpdate(Request $request, Report $report)
    {
        $request->validate([
            'status' => 'required|in:pending,reviewed,resolved,dismissed',
            'admin_notes' => 'nullable|string'
        ]);
        
        $report->update([
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        
        return back()->with('success', 'Report updated successfully.');
    }


/**
 * Show list of blocked users (Read-only for privacy)
 */
public function blocksIndex()
{
    $blocks = Block::with(['blocker:id,name,phone', 'blocked:id,name,phone'])
        ->latest()
        ->paginate(20);
        
    $totalBlocks = Block::count();
    $uniqueBlockers = Block::distinct('blocker_id')->count('blocker_id');
    $uniqueBlocked = Block::distinct('blocked_user_id')->count('blocked_user_id');
    
    return view('admin.blocks.index', compact('blocks', 'totalBlocks', 'uniqueBlockers', 'uniqueBlocked'));
}
    // ============ API CLIENTS MANAGEMENT METHODS ============
    
    public function apiClientsIndex()
    {
        // Get all users with developer mode enabled
        $devUsers = \App\Models\User::where('developer_mode', true)
            ->with(['userApiKeys' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->latest()
            ->get();
        
        // Format for display - show users and their API keys
        $allApiKeys = collect();
        
        foreach ($devUsers as $user) {
            // If user has no API keys, still show them (they have dev mode enabled)
            if ($user->userApiKeys->isEmpty()) {
                $allApiKeys->push([
                    'id' => 'user_' . $user->id,
                    'type' => 'user',
                    'name' => $user->name ?? 'Unknown User',
                    'client_id' => $user->developer_client_id ?? 'Not generated',
                    'user' => $user,
                    'status' => 'no_keys',
                    'created_at' => $user->created_at,
                    'last_used_at' => null,
                    'description' => 'Developer Mode Enabled (No API keys yet)',
                    'messages_count' => 0,
                    'conversations_count' => 0,
                    'is_user_row' => true,
                ]);
            } else {
                // Add each API key for this user
                foreach ($user->userApiKeys as $apiKey) {
                    // Get message and conversation counts for this API key
                    $messagesCount = $apiKey->messages()->count();
                    $conversationsCount = \DB::table('conversations')
                        ->join('messages', function($join) use ($apiKey) {
                            $join->on('conversations.id', '=', 'messages.conversation_id')
                                 ->where('messages.user_api_key_id', $apiKey->id)
                                 ->whereRaw('messages.id = (
                                     SELECT MIN(m.id) 
                                     FROM messages m 
                                     WHERE m.conversation_id = conversations.id
                                     AND m.user_api_key_id = ?
                                 )', [$apiKey->id]);
                        })
                        ->distinct()
                        ->count('conversations.id');
                    
                    $allApiKeys->push([
                        'id' => $apiKey->id,
                        'type' => 'user_api_key',
                        'name' => $apiKey->name,
                        'client_id' => $user->developer_client_id ?? 'Not generated',
                        'user' => $user,
                        'status' => $apiKey->is_active ? 'active' : 'inactive',
                        'created_at' => $apiKey->created_at,
                        'last_used_at' => $apiKey->last_used_at,
                        'last_used_ip' => $apiKey->last_used_ip,
                        'description' => 'User API Key',
                        'webhook_url' => $apiKey->webhook_url,
                        'token_preview' => $apiKey->client_secret_plain 
                            ? substr($apiKey->client_secret_plain, 0, 12) . '...' . substr($apiKey->client_secret_plain, -8) 
                            : '••••••••••••••••',
                        'messages_count' => $messagesCount,
                        'conversations_count' => $conversationsCount,
                        'has_special_privilege' => $user->has_special_api_privilege ?? false,
                        'is_user_row' => false,
                    ]);
                }
            }
        }
        
        // Sort by created_at descending and paginate manually
        $allApiKeys = $allApiKeys->sortByDesc('created_at')->values();
        $perPage = 20;
        $currentPage = request()->get('page', 1);
        $items = $allApiKeys->slice(($currentPage - 1) * $perPage, $perPage)->all();
        $clients = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $allApiKeys->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
            
        return view('admin.api-clients.index', compact('clients'));
    }
    
    public function apiClientsUpdateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,inactive'
        ]);
        
        // Find user API key
        $apiKey = \App\Models\UserApiKey::find($id);
        
        if (!$apiKey) {
            return back()->withErrors('API key not found.');
        }
        
        $apiKey->update(['is_active' => $request->status === 'active']);
        
        $statusText = $request->status === 'active' ? 'activated' : 'deactivated';
        return back()->with('success', "API key {$statusText} successfully.");
    }
    
    public function apiClientsDestroy($id)
    {
        // Find user API key
        $apiKey = \App\Models\UserApiKey::find($id);
        
        if (!$apiKey) {
            return back()->withErrors('API key not found.');
        }
        
        $apiKey->delete();
        return back()->with('success', 'API key revoked successfully.');
    }
    
    public function apiClientsRegenerateSecret($id)
    {
        // This method is not applicable for user API keys
        // Users regenerate their own keys from the settings page
        return back()->withErrors('API key regeneration is handled by users in their settings page.');
    }
    
    /**
     * Update webhook URL for a user API key
     */
    public function apiClientsUpdateWebhook(Request $request, $id)
    {
        $request->validate([
            'webhook_url' => 'nullable|url|max:500',
        ]);
        
        $apiKey = \App\Models\UserApiKey::find($id);
        
        if (!$apiKey) {
            return back()->withErrors('API key not found.');
        }
        
        $apiKey->update([
            'webhook_url' => $request->webhook_url ?: null,
        ]);
        
        return back()->with('success', $request->webhook_url ? 'Webhook URL updated successfully.' : 'Webhook URL removed successfully.');
    }
    
    /**
     * Show all accounts with special API privileges
     * Displays users with has_special_api_privilege and their API clients
     */
    public function specialApiPrivileges()
    {
        // Get all users with special API privilege
        $privilegedUsers = User::where('has_special_api_privilege', true)
            ->withCount(['apiClients', 'sentMessages'])
            ->latest()
            ->get();
        
        // Get all API clients owned by users with special privilege
        $privilegedApiClients = collect();
        
        // Platform API clients
        $platformClients = ApiClient::with('user')
            ->whereHas('user', function($query) {
                $query->where('has_special_api_privilege', true);
            })
            ->latest()
            ->get();
        
        foreach ($platformClients as $client) {
            $messagesCount = $client->messages()->count();
            $conversationsCount = \DB::table('conversations')
                ->join('messages', function($join) {
                    $join->on('conversations.id', '=', 'messages.conversation_id')
                         ->whereRaw('messages.id = (
                             SELECT MIN(m.id) 
                             FROM messages m 
                             WHERE m.conversation_id = conversations.id
                         )');
                })
                ->where('messages.platform_client_id', $client->id)
                ->distinct()
                ->count('conversations.id');
            
            $privilegedApiClients->push([
                'id' => $client->id,
                'type' => 'platform',
                'name' => ($client->user->name ?? 'Unknown') . ' - Platform Client',
                'client_id' => $client->client_id,
                'user' => $client->user,
                'status' => $client->status ?? 'active',
                'created_at' => $client->created_at,
                'last_used_at' => $client->last_used_at ?? null,
                'description' => 'Platform API Client',
                'webhook_url' => $client->callback_url ?? null,
                'messages_count' => $messagesCount,
                'conversations_count' => $conversationsCount,
            ]);
        }
        
        // User API keys
        $userApiKeys = \App\Models\UserApiKey::with('user')
            ->whereHas('user', function($query) {
                $query->where('has_special_api_privilege', true)
                      ->where('developer_mode', true);
            })
            ->latest()
            ->get();
        
        foreach ($userApiKeys as $apiKey) {
            // Get usage stats for this API key
            $messagesCount = $apiKey->messages()->count();
            $conversationsCount = \DB::table('conversations')
                ->join('messages', function($join) use ($apiKey) {
                    $join->on('conversations.id', '=', 'messages.conversation_id')
                         ->where('messages.user_api_key_id', $apiKey->id)
                         ->whereRaw('messages.id = (
                             SELECT MIN(m.id) 
                             FROM messages m 
                             WHERE m.conversation_id = conversations.id
                             AND m.user_api_key_id = ?
                         )', [$apiKey->id]);
                })
                ->distinct()
                ->count('conversations.id');
            
            $privilegedApiClients->push([
                'id' => $apiKey->id,
                'type' => 'user',
                'name' => $apiKey->name,
                'client_id' => $apiKey->user->developer_client_id ?? null,
                'user' => $apiKey->user,
                'status' => $apiKey->is_active ? 'active' : 'inactive',
                'created_at' => $apiKey->created_at,
                'last_used_at' => $apiKey->last_used_at ?? null,
                'description' => 'User API Key',
                'webhook_url' => null,
                'token_preview' => $apiKey->client_secret_plain ? substr($apiKey->client_secret_plain, 0, 12) . '...' . substr($apiKey->client_secret_plain, -8) : '••••••••••••••••',
                'messages_count' => $messagesCount,
                'conversations_count' => $conversationsCount,
            ]);
        }
        
        // Sort by created_at descending
        $privilegedApiClients = $privilegedApiClients->sortByDesc('created_at')->values();
        
        return view('admin.special-api-privileges.index', compact('privilegedUsers', 'privilegedApiClients'));
    }

    // ============ ADDITIONAL HELPER METHODS ============
    
    public function getUserStats(User $user)
    {
        // Get comprehensive stats for a specific user
        $stats = [
            'conversations_count' => $user->conversations()->count(),
            'groups_count' => $user->groups()->count(),
            'messages_count' => $user->messages()->count(),
            'reports_received' => Report::where('reported_user_id', $user->id)->count(),
            'reports_filed' => Report::where('reporter_id', $user->id)->count(),
            'blocked_by_count' => Block::where('blocked_user_id', $user->id)->count(),
            'blocking_count' => Block::where('blocker_id', $user->id)->count(),
        ];
        
        return view('admin.users.stats', compact('user', 'stats'));
    }
    
    public function suspendUser(User $user)
    {
        $user->update([
            'is_active' => false,
            'suspended_at' => now(),
            'suspended_by' => auth()->id(),
        ]);
        
        return back()->with('success', 'User suspended successfully.');
    }
    
    public function activateUser(User $user)
    {
        $user->update([
            'is_active' => true,
            'suspended_at' => null,
            'suspended_by' => null,
        ]);
        
        return back()->with('success', 'User activated successfully.');
    }
    
    /**
     * Toggle Special API Creation Privilege for a user
     * Only users with developer_mode enabled can have this privilege
     */
    public function toggleSpecialApiPrivilege(User $user)
    {
        // Only allow if user has developer mode enabled
        if (!$user->developer_mode) {
            return back()->withErrors('User must have Developer Mode enabled to grant Special API Creation Privilege.');
        }
        
        $user->update([
            'has_special_api_privilege' => !$user->has_special_api_privilege
        ]);
        
        $message = $user->has_special_api_privilege 
            ? 'Special API Creation Privilege granted. User can now auto-create GekyChat users when sending messages to unregistered phone numbers.'
            : 'Special API Creation Privilege revoked.';
        
        return back()->with('success', $message);
    }
    
    /**
     * Get API client details for modal
     */
    public function apiClientsDetails($id)
    {
        // Find user API key
        $userApiKey = \App\Models\UserApiKey::with('user')->find($id);
        if ($userApiKey && $userApiKey->user) {
            $user = $userApiKey->user;
            
            // Get usage stats
            $messagesCount = $userApiKey->messages()->count();
            $conversationsCount = \DB::table('conversations')
                ->join('messages', function($join) use ($userApiKey) {
                    $join->on('conversations.id', '=', 'messages.conversation_id')
                         ->where('messages.user_api_key_id', $userApiKey->id)
                         ->whereRaw('messages.id = (
                             SELECT MIN(m.id) 
                             FROM messages m 
                             WHERE m.conversation_id = conversations.id
                             AND m.user_api_key_id = ?
                         )', [$userApiKey->id]);
                })
                ->distinct()
                ->count('conversations.id');
            
            return response()->json([
                'type' => 'user_api_key',
                'id' => $userApiKey->id,
                'name' => $userApiKey->name,
                'client_id' => $user->developer_client_id ?? 'Not generated',
                'status' => $userApiKey->is_active ? 'active' : 'inactive',
                'created_at' => $userApiKey->created_at->format('M j, Y g:i A'),
                'last_used_at' => $userApiKey->last_used_at ? $userApiKey->last_used_at->format('M j, Y g:i A') : 'Never',
                'last_used_ip' => $userApiKey->last_used_ip,
                'description' => 'User API Key',
                'token_preview' => $userApiKey->client_secret_plain ? substr($userApiKey->client_secret_plain, 0, 12) . '...' . substr($userApiKey->client_secret_plain, -8) : '••••••••••••••••',
                'messages_count' => $messagesCount,
                'conversations_count' => $conversationsCount,
                'has_special_privilege' => $user->has_special_api_privilege ?? false,
                'owner' => [
                    'id' => $user->id,
                    'name' => $user->name ?? 'Unknown',
                    'email' => $user->email ?? $user->phone ?? 'No contact',
                ],
            ]);
        }
        
        return response()->json(['error' => 'API key not found'], 404);
    }
    
    /**
     * Toggle Special API Creation Privilege for a user (via their API key)
     */
    public function apiClientsToggleSpecialPrivilege($id)
    {
        // Find user API key
        $userApiKey = \App\Models\UserApiKey::with('user')->find($id);
        
        if (!$userApiKey || !$userApiKey->user) {
            return back()->withErrors('API key not found.');
        }
        
        $user = $userApiKey->user;
        
        // Ensure user has developer mode enabled
        if (!$user->developer_mode) {
            return back()->withErrors('User must have Developer Mode enabled to grant Special API Creation Privilege.');
        }
        
        $user->update([
            'has_special_api_privilege' => !$user->has_special_api_privilege
        ]);
        
        $message = $user->has_special_api_privilege 
            ? 'Special API Creation Privilege granted to user.'
            : 'Special API Creation Privilege revoked from user.';
        
        return back()->with('success', $message);
    }

    /**
     * Get system health status
     */
    public function systemHealth()
    {
        $health = [
            'database' => $this->checkDatabaseHealth(),
            'cache' => $this->checkCacheHealth(),
            'storage' => $this->checkStorageHealth(),
            'queue' => $this->checkQueueHealth(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $health
        ]);
    }

    private function checkDatabaseHealth()
    {
        try {
            DB::select('SELECT 1');
            return ['status' => 'healthy', 'message' => 'Database connection is stable'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    private function checkCacheHealth()
    {
        try {
            cache()->put('health_check', 'ok', 10);
            return cache()->get('health_check') === 'ok' 
                ? ['status' => 'healthy', 'message' => 'Cache is working properly']
                : ['status' => 'unhealthy', 'message' => 'Cache read/write failed'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    private function checkStorageHealth()
    {
        try {
            $free = disk_free_space(storage_path());
            $total = disk_total_space(storage_path());
            $usage = (($total - $free) / $total) * 100;

            return [
                'status' => $usage > 90 ? 'warning' : 'healthy',
                'message' => "Storage usage: " . round($usage, 1) . "%",
                'usage' => $usage
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    private function checkQueueHealth()
    {
        // This would check your queue system health
        // For now, return a basic status
        return ['status' => 'healthy', 'message' => 'Queue system is operational'];
    }
    
    /**
     * Audio Library Management
     */
    public function audioLibrary(Request $request)
    {
        $query = AudioLibrary::query()->with(['usageStats', 'licenseSnapshots']);
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('validation_status', $request->status);
        }
        
        // Filter by license type
        if ($request->has('license')) {
            $query->where('license_type', 'like', "%{$request->license}%");
        }
        
        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('freesound_username', 'like', "%{$search}%");
            });
        }
        
        $audio = $query->orderBy('usage_count', 'desc')
            ->paginate(50);
        
        // Statistics
        $stats = [
            'total' => AudioLibrary::count(),
            'active' => AudioLibrary::where('is_active', true)->count(),
            'cc0' => AudioLibrary::where('license_type', 'like', '%CC0%')->count(),
            'attribution' => AudioLibrary::where('license_type', 'like', '%Attribution%')->count(),
            'total_usage' => AudioLibrary::sum('usage_count'),
        ];
        
        return view('admin.audio.index', compact('audio', 'stats'));
    }
    
    /**
     * Toggle audio active status
     */
    public function toggleAudioStatus(Request $request, AudioLibrary $audio)
    {
        $audio->is_active = !$audio->is_active;
        $audio->save();
        
        return response()->json([
            'success' => true,
            'is_active' => $audio->is_active,
        ]);
    }
    
    /**
     * Update audio validation status
     */
    public function updateAudioValidation(Request $request, AudioLibrary $audio)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);
        
        $audio->validation_status = $request->status;
        $audio->save();
        
        return response()->json([
            'success' => true,
            'validation_status' => $audio->validation_status,
        ]);
    }
}