@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
<div class="space-y-6">
    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Users -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Users</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalUsers }}</p>
                    <p class="text-xs text-green-600 dark:text-green-400">
                        +{{ $userGrowth['growth_30d'] }} this month
                    </p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg">
                    <i class="fas fa-users text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Active Today -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Today</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $userGrowth['active_today'] }}</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">
                        {{ $engagementMetrics['dau'] }} currently online
                    </p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900 rounded-lg">
                    <i class="fas fa-user-check text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Messages Today -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Messages Today</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
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
        </div>

        <!-- AI Interactions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">AI Interactions</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $aiChatAnalytics['today_interactions'] }}</p>
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

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- User Growth Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">User Growth (30 Days)</h3>
            <div class="h-64">
                <canvas id="userGrowthChart"></canvas>
            </div>
        </div>

        <!-- Message Distribution -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Message Distribution</h3>
            <div class="h-64">
                <canvas id="messageDistributionChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Engagement Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Engagement</h4>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">DAU/MAU Ratio</span>
                    <span class="font-semibold text-gray-900 dark:text-white">
                        {{ $engagementMetrics['mau'] > 0 ? round(($engagementMetrics['dau'] / $engagementMetrics['mau']) * 100, 1) : 0 }}%
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Retention Rate</span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $userGrowth['retention_rate'] }}%</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Avg Session</span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $engagementMetrics['avg_session_duration'] }}m</span>
                </div>
            </div>
        </div>

        <!-- Platform Usage -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Platform Usage</h4>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Active Groups</span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $platformUsage['active_groups'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Avg Group Size</span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $platformUsage['avg_group_size'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">New Groups Today</span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $platformUsage['groups_created_today'] }}</span>
                </div>
            </div>
        </div>

        <!-- Real-time Activity -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Live Activity</h4>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Online Now</span>
                    <span class="font-semibold text-green-600">{{ $realtimeActivity['online_users'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Typing Now</span>
                    <span class="font-semibold text-blue-600">{{ $realtimeActivity['typing_now'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">New Users Today</span>
                    <span class="font-semibold text-purple-600">{{ $realtimeActivity['new_users_today'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Analytics Section -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Geky AI Analytics</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="text-center p-4 bg-blue-50 dark:bg-blue-900 rounded-lg">
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $aiChatAnalytics['total_interactions'] }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Total Interactions</div>
            </div>
            <div class="text-center p-4 bg-green-50 dark:bg-green-900 rounded-lg">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $aiChatAnalytics['today_interactions'] }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Today</div>
            </div>
            <div class="text-center p-4 bg-purple-50 dark:bg-purple-900 rounded-lg">
                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $aiChatAnalytics['active_bot_users'] }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Active Users</div>
            </div>
            <div class="text-center p-4 bg-orange-50 dark:bg-orange-900 rounded-lg">
                <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $aiChatAnalytics['user_satisfaction'] }}%</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Satisfaction</div>
            </div>
        </div>
        
        <!-- Popular Commands -->
        @if(count($aiChatAnalytics['popular_commands']) > 0)
        <div class="mt-6">
            <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Popular Commands</h4>
            <div class="space-y-2">
                @foreach($aiChatAnalytics['popular_commands'] as $command)
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-600 dark:text-gray-400">{{ $command['command'] }}</span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $command['count'] }} uses</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <!-- Recent Activity -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Activity</h3>
        <div class="space-y-3">
            @foreach($realtimeActivity['recent_messages'] as $activity)
            <div class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                    {{ substr($activity->sender->name ?? 'U', 0, 1) }}
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ $activity->sender->name ?? 'Unknown User' }}
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-400 truncate">
                        {{ $activity->body ?? 'No content' }}
                    </p>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $activity->created_at->diffForHumans() }}
                </div>
            </div>
            @endforeach
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
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
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
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Auto-refresh data every 2 minutes
setInterval(() => {
    fetch('/admin/api/refresh-data')
        .then(response => response.json())
        .then(data => {
            // Update charts and metrics here
            console.log('Data refreshed', data);
        })
        .catch(error => console.error('Refresh failed:', error));
}, 120000);
</script>
@endsection