@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('breadcrumb')
    <li class="inline-flex items-center">
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Dashboard</span>
    </li>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Header with Quick Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard Overview</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Real-time analytics and system metrics</p>
        </div>
        <div class="flex items-center space-x-3 mt-4 sm:mt-0">
            <button id="manualRefresh" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-sync-alt mr-2"></i>
                Refresh
            </button>
            <a href="{{ route('admin.system.health') }}" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-heartbeat mr-2"></i>
                System Health
            </a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Users -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-blue-500 hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Users</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white" data-metric="total_users">{{ $totalUsers }}</p>
                    <p class="text-xs text-green-600 dark:text-green-400">
                        <i class="fas fa-arrow-up mr-1"></i>+{{ $userGrowth['growth_30d'] }} this month
                    </p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg">
                    <i class="fas fa-users text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center text-xs text-gray-500 dark:text-gray-400">
                <i class="fas fa-clock mr-1"></i>
                Updated: <span id="lastUpdateTime" class="ml-1">{{ now()->format('H:i:s') }}</span>
            </div>
        </div>

        <!-- Active Today -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-green-500 hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Today</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white" data-metric="active_today">{{ $userGrowth['active_today'] }}</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">
                        <span class="text-green-600 font-semibold" data-metric="online_users">{{ $engagementMetrics['dau'] }}</span> currently online
                    </p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900 rounded-lg">
                    <i class="fas fa-user-check text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Messages Today -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-purple-500 hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Messages Today</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white" data-metric="messages_today">
                        {{ $messageStats['dm_today'] + $messageStats['group_today'] }}
                    </p>
                    <p class="text-xs text-purple-600 dark:text-purple-400">
                        {{ $messageStats['dm_today'] }} DM + {{ $messageStats['group_today'] }} Group
                    </p>
                </div>
                <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-lg">
                    <i class="fas fa-comments text-purple-600 dark:text-purple-400 text-xl"></i>
                </div>
            </div>
            <div class="mt-2 flex space-x-4 text-xs">
                <span class="text-green-600">
                    <i class="fas fa-arrow-up mr-1"></i>{{ $messageStats['dm_growth'] }}% DM
                </span>
                <span class="text-blue-600">
                    <i class="fas fa-arrow-up mr-1"></i>{{ $messageStats['group_growth'] }}% Group
                </span>
            </div>
        </div>

        <!-- AI Interactions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-orange-500 hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">AI Interactions</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white" data-metric="ai_interactions">{{ $aiChatAnalytics['today_interactions'] }}</p>
                    <p class="text-xs text-orange-600 dark:text-orange-400">
                        {{ $aiChatAnalytics['user_satisfaction'] }}% satisfaction
                    </p>
                </div>
                <div class="p-3 bg-orange-100 dark:bg-orange-900 rounded-lg">
                    <i class="fas fa-robot text-orange-600 dark:text-orange-400 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Second Row Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- API Clients -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-teal-500 hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">API Clients</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $apiClientCount }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Registered integrations</p>
                </div>
                <div class="p-3 bg-teal-100 dark:bg-teal-900 rounded-lg">
                    <i class="fas fa-plug text-teal-600 dark:text-teal-400 text-xl"></i>
                </div>
            </div>
            <a href="{{ route('admin.api-clients.index') }}" class="mt-3 inline-flex items-center text-teal-600 dark:text-teal-400 text-sm font-medium hover:underline">
                Manage Clients
                <i class="fas fa-arrow-right ml-1 text-xs"></i>
            </a>
        </div>

        <!-- Pending Reports -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-red-500 hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pending Reports</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white" data-metric="pending_reports">{{ $reportedCount }}</p>
                    <p class="text-xs text-red-600 dark:text-red-400">
                        Requires immediate attention
                    </p>
                </div>
                <div class="p-3 bg-red-100 dark:bg-red-900 rounded-lg">
                    <i class="fas fa-flag text-red-600 dark:text-red-400 text-xl"></i>
                </div>
            </div>
            <a href="{{ route('admin.reports.index') }}" class="mt-3 inline-flex items-center text-red-600 dark:text-red-400 text-sm font-medium hover:underline">
                Review Reports
                <i class="fas fa-arrow-right ml-1 text-xs"></i>
            </a>
        </div>

        <!-- Active Groups -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-indigo-500 hover-lift">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Groups</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $platformUsage['active_groups'] }}</p>
                    <p class="text-xs text-indigo-600 dark:text-indigo-400">
                        Avg {{ $platformUsage['avg_group_size'] }} members
                    </p>
                </div>
                <div class="p-3 bg-indigo-100 dark:bg-indigo-900 rounded-lg">
                    <i class="fas fa-users text-indigo-600 dark:text-indigo-400 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- User Growth Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 hover-lift">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">User Growth (30 Days)</h3>
                <div class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
                    <i class="fas fa-chart-line"></i>
                    <span>Trend Analysis</span>
                </div>
            </div>
            <div class="h-64">
                <canvas id="userGrowthChart"></canvas>
            </div>
        </div>

        <!-- Message Distribution -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 hover-lift">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Message Distribution</h3>
                <div class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
                    <i class="fas fa-chart-pie"></i>
                    <span>Channel Analysis</span>
                </div>
            </div>
            <div class="h-64">
                <canvas id="messageDistributionChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Engagement & Platform Metrics -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Engagement Metrics -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 hover-lift">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-semibold text-gray-900 dark:text-white">Engagement Metrics</h4>
                <i class="fas fa-chart-bar text-blue-500"></i>
            </div>
            <div class="space-y-4">
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">DAU/MAU Ratio</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Daily vs Monthly Active</p>
                        </div>
                    </div>
                    <span class="text-lg font-bold text-blue-600 dark:text-blue-400">
                        {{ $engagementMetrics['mau'] > 0 ? round(($engagementMetrics['dau'] / $engagementMetrics['mau']) * 100, 1) : 0 }}%
                    </span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                            <i class="fas fa-retweet text-green-600 dark:text-green-400"></i>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Retention Rate</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400">User stickiness</p>
                        </div>
                    </div>
                    <span class="text-lg font-bold text-green-600 dark:text-green-400">{{ $userGrowth['retention_rate'] }}%</span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clock text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Session</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Engagement duration</p>
                        </div>
                    </div>
                    <span class="text-lg font-bold text-purple-600 dark:text-purple-400">{{ $engagementMetrics['avg_session_duration'] }}m</span>
                </div>
            </div>
        </div>

        <!-- Platform Usage -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 hover-lift">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-semibold text-gray-900 dark:text-white">Platform Usage</h4>
                <i class="fas fa-layer-group text-green-500"></i>
            </div>
            <div class="space-y-4">
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Groups</span>
                        <span class="text-lg font-bold text-green-600 dark:text-green-400">{{ $platformUsage['active_groups'] }}</span>
                    </div>
                    <div class="mt-2 w-full bg-green-200 dark:bg-green-800 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: {{ min(($platformUsage['active_groups'] / max($platformUsage['total_groups'], 1)) * 100, 100) }}%"></div>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-blue-50 to-cyan-50 dark:from-blue-900/20 dark:to-cyan-900/20 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Group Size</span>
                        <span class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ $platformUsage['avg_group_size'] }}</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Members per group</p>
                </div>
                
                <div class="bg-gradient-to-r from-purple-50 to-violet-50 dark:from-purple-900/20 dark:to-violet-900/20 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">New Groups Today</span>
                        <span class="text-lg font-bold text-purple-600 dark:text-purple-400">{{ $platformUsage['groups_created_today'] }}</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Community growth</p>
                </div>
            </div>
        </div>

        <!-- Real-time Activity -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 hover-lift">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-semibold text-gray-900 dark:text-white">Live Activity</h4>
                <div class="flex items-center space-x-1 text-green-500">
                    <i class="fas fa-circle animate-pulse"></i>
                    <i class="fas fa-bolt"></i>
                </div>
            </div>
            <div class="space-y-4">
                <div class="flex justify-between items-center p-3 bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-wifi text-white text-xs"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Online Now</span>
                    </div>
                    <span class="text-lg font-bold text-green-600 dark:text-green-400">{{ $realtimeActivity['online_users'] }}</span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-gradient-to-r from-blue-50 to-cyan-50 dark:from-blue-900/20 dark:to-cyan-900/20 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-keyboard text-white text-xs"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Typing Now</span>
                    </div>
                    <span class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ $realtimeActivity['typing_now'] }}</span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-gradient-to-r from-purple-50 to-violet-50 dark:from-purple-900/20 dark:to-violet-900/20 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-user-plus text-white text-xs"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">New Users Today</span>
                    </div>
                    <span class="text-lg font-bold text-purple-600 dark:text-purple-400">{{ $realtimeActivity['new_users_today'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Analytics Section -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 hover-lift">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Geky AI Analytics</h3>
            <div class="flex items-center space-x-2 text-orange-500">
                <i class="fas fa-robot"></i>
                <span class="text-sm font-medium">AI Performance</span>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="text-center p-4 bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/30 rounded-lg hover-lift">
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $aiChatAnalytics['total_interactions'] }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Total Interactions</div>
                <div class="text-xs text-blue-500 dark:text-blue-300 mt-1">
                    <i class="fas fa-database"></i> All-time
                </div>
            </div>
            <div class="text-center p-4 bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/30 rounded-lg hover-lift">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $aiChatAnalytics['today_interactions'] }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Today</div>
                <div class="text-xs text-green-500 dark:text-green-300 mt-1">
                    <i class="fas fa-calendar-day"></i> 24h
                </div>
            </div>
            <div class="text-center p-4 bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/30 rounded-lg hover-lift">
                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $aiChatAnalytics['active_bot_users'] }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Active Users</div>
                <div class="text-xs text-purple-500 dark:text-purple-300 mt-1">
                    <i class="fas fa-users"></i> Engaging
                </div>
            </div>
            <div class="text-center p-4 bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-900/20 dark:to-orange-800/30 rounded-lg hover-lift">
                <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $aiChatAnalytics['user_satisfaction'] }}%</div>
                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Satisfaction</div>
                <div class="text-xs text-orange-500 dark:text-orange-300 mt-1">
                    <i class="fas fa-star"></i> Rating
                </div>
            </div>
        </div>
        
        <!-- Popular Commands -->
        @if(count($aiChatAnalytics['popular_commands']) > 0)
        <div class="mt-6">
            <h4 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <i class="fas fa-fire text-orange-500 mr-2"></i>
                Popular Commands
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($aiChatAnalytics['popular_commands'] as $index => $command)
                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-gradient-to-r from-orange-500 to-red-500 rounded-full flex items-center justify-center text-white text-xs font-bold">
                            {{ $index + 1 }}
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate" title="{{ $command['command'] }}">
                            {{ $command['command'] }}
                        </span>
                    </div>
                    <span class="bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200 text-xs font-medium px-2 py-1 rounded-full">
                        {{ $command['count'] }} uses
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <!-- Recent Activity & System Status -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Activity -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 hover-lift">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Activity</h3>
                <a href="{{ route('admin.messages') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                    View All
                </a>
            </div>
            <div class="space-y-3">
                @forelse($realtimeActivity['recent_messages'] as $activity)
                <div class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                        {{ substr($activity->sender->name ?? 'U', 0, 1) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                            {{ $activity->sender->name ?? 'Unknown User' }}
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 truncate">
                            {{ $activity->body ?? 'No content' }}
                        </p>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">
                        {{ $activity->created_at->diffForHumans() }}
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2"></i>
                    <p>No recent activity</p>
                </div>
                @endforelse
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 hover-lift">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
            <div class="grid grid-cols-2 gap-4">
                <a href="{{ route('admin.users.index') }}" class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors group">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                            <i class="fas fa-users text-white"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">User Management</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Manage users</p>
                        </div>
                    </div>
                </a>
                
                <a href="{{ route('admin.reports.index') }}" class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors group">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-red-500 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                            <i class="fas fa-flag text-white"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Reports</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $reportedCount }} pending</p>
                        </div>
                    </div>
                </a>
                
                <a href="{{ route('admin.api-clients.index') }}" class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors group">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                            <i class="fas fa-plug text-white"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">API Clients</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Integrations</p>
                        </div>
                    </div>
                </a>
                
                <a href="{{ route('admin.settings') }}" class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors group">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                            <i class="fas fa-cog text-white"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Settings</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">System config</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// User Growth Chart
const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
const userGrowthChart = new Chart(userGrowthCtx, {
    type: 'line',
    data: {
        labels: {!! json_encode(array_keys($userGrowth['daily_registrations']->toArray())) !!},
        datasets: [{
            label: 'New Users',
            data: {!! json_encode(array_values($userGrowth['daily_registrations']->toArray())) !!},
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderWidth: 3,
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#3b82f6',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleFont: {
                    size: 12
                },
                bodyFont: {
                    size: 11
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)'
                },
                ticks: {
                    font: {
                        size: 11
                    }
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: {
                        size: 11
                    },
                    maxRotation: 45
                }
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        }
    }
});

// Message Distribution Chart
const messageDistributionCtx = document.getElementById('messageDistributionChart').getContext('2d');
const messageDistributionChart = new Chart(messageDistributionCtx, {
    type: 'doughnut',
    data: {
        labels: ['Direct Messages', 'Group Messages'],
        datasets: [{
            data: [{{ $messageStats['total_dm'] }}, {{ $messageStats['total_group'] }}],
            backgroundColor: [
                '#10b981',
                '#8b5cf6'
            ],
            borderWidth: 3,
            borderColor: '#ffffff',
            hoverOffset: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    font: {
                        size: 12
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                bodyFont: {
                    size: 11
                }
            }
        }
    }
});

// Manual refresh button handler
document.getElementById('manualRefresh')?.addEventListener('click', function() {
    const btn = this;
    const originalHtml = btn.innerHTML;
    
    // Show loading state
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Refreshing...';
    btn.disabled = true;
    
    // Perform refresh
    performAutoRefresh();
    
    // Restore button after 2 seconds
    setTimeout(() => {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    }, 2000);
});

// Enhanced data update function
function updateDashboardData(data) {
    // Update all metric elements
    const metrics = {
        'online_users': data.online_users,
        'messages_today': data.messages_today.total,
        'new_users_today': data.new_users_today,
        'pending_reports': data.pending_reports,
        'ai_interactions': data.ai_interactions_today
    };
    
    Object.entries(metrics).forEach(([metric, value]) => {
        const element = document.querySelector(`[data-metric="${metric}"]`);
        if (element) {
            // Add animation
            element.style.transform = 'scale(1.1)';
            setTimeout(() => {
                element.textContent = value;
                element.style.transform = 'scale(1)';
            }, 150);
        }
    });
    
    // Update last update time
    const timeElement = document.getElementById('lastUpdateTime');
    if (timeElement) {
        timeElement.textContent = new Date().toLocaleTimeString();
    }
}

// Auto-refresh data every 2 minutes
setInterval(performAutoRefresh, 120000);

// Initial data refresh after page load
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(performAutoRefresh, 3000);
});
</script>
@endsection