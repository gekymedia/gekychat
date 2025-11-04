<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Group;
use App\Models\GroupMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminController extends Controller
{
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
            ? User::where(function($query) {
                $query->whereNull('status')->orWhere('status', 'active');
            })->count()
            : $totalUsers;

        $publicChannels = Conversation::where('is_group', true)
            ->where('is_private', false)
            ->count();

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
        $activeGroups = Group::whereHas('messages', function($query) {
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
     * Get AI Chat analytics - FIXED VERSION
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
        return [
            'online_users' => User::where('last_seen_at', '>=', Carbon::now()->subMinutes(5))->count(),
            'typing_now' => $this->getTypingUsers(),
            'recent_messages' => $this->getRecentMessages(),
            'new_users_today' => User::whereDate('created_at', Carbon::today())->count(),
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
     * Get average group size - FIXED VERSION
     */
    private function getAverageGroupSize()
    {
        try {
            $result = DB::table('group_members')
                ->select(DB::raw('AVG(member_count) as avg_size'))
                ->fromSub(function($query) {
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
            return Group::withCount(['messages' => function($query) {
                $query->where('created_at', '>=', Carbon::now()->subDays(7));
            }])
            ->orderByDesc('messages_count')
            ->limit(5)
            ->get()
            ->map(function($group) {
                return [
                    'name' => $group->name,
                    'message_count' => $group->messages_count,
                    'member_count' => $group->members()->count(),
                ];
            });
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Get popular bot commands - FIXED VERSION
     */
    private function getPopularBotCommands($botUserId)
    {
        try {
            // Get conversations where the bot is a member
            $botConversations = Conversation::whereHas('members', function($query) use ($botUserId) {
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
                ->map(function($message) {
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
     * Get active bot users - FIXED VERSION
     */
    private function getActiveBotUsers($botUserId)
    {
        try {
            // Get conversations where the bot is a member
            $botConversations = Conversation::whereHas('members', function($query) use ($botUserId) {
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
}