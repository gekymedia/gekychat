@extends('layouts.admin')

@section('title', 'User Analytics — #' . $detail['user']['id'])

@section('content')
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <a href="{{ route('admin.product-analytics.index', ['period' => $period]) }}" class="text-sm text-indigo-600 hover:underline">← Product Analytics</a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2">
                {{ $detail['user']['name'] ?? 'User' }} <span class="text-gray-400 font-normal">#{{ $detail['user']['id'] }}</span>
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                {{ $detail['user']['phone'] }}
                @if($detail['user']['email']) · {{ $detail['user']['email'] }} @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            @foreach(['7d' => '7d', '30d' => '30d', '90d' => '90d'] as $key => $label)
                <a href="{{ route('admin.product-analytics.user', ['userId' => $detail['user']['id'], 'period' => $key]) }}"
                   class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $period === $key ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow">
            <div class="text-xs text-gray-500 uppercase">Joined</div>
            <div class="text-lg font-bold text-gray-900 dark:text-white mt-1">
                {{ $detail['user']['created_at'] ? \Carbon\Carbon::parse($detail['user']['created_at'])->format('M j, Y') : '—' }}
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow">
            <div class="text-xs text-gray-500 uppercase">Last seen</div>
            <div class="text-lg font-bold text-gray-900 dark:text-white mt-1">
                {{ $detail['user']['last_seen_at'] ? \Carbon\Carbon::parse($detail['user']['last_seen_at'])->diffForHumans() : 'Never' }}
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow">
            <div class="text-xs text-gray-500 uppercase">Sessions (period)</div>
            <div class="text-lg font-bold text-gray-900 dark:text-white mt-1">{{ number_format($detail['sessions']) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow">
            <div class="text-xs text-gray-500 uppercase">Hours in app</div>
            <div class="text-lg font-bold text-gray-900 dark:text-white mt-1">{{ number_format($detail['hours_in_app'], 1) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Funnel milestones</h3>
            <ul class="space-y-3 text-sm">
                <li class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">First message sent</span>
                    <span class="font-medium text-gray-900 dark:text-white">
                        @if($detail['milestones']['sent_first_message'])
                            {{ \Carbon\Carbon::parse($detail['milestones']['first_message_at'])->format('M j, Y') }}
                        @else
                            <span class="text-gray-400">Not yet</span>
                        @endif
                    </span>
                </li>
                <li class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Day-7 return</span>
                    <span class="font-medium {{ $detail['milestones']['returned_day_7'] ? 'text-emerald-600' : 'text-gray-400' }}">
                        {{ $detail['milestones']['returned_day_7'] ? 'Yes' : ($detail['user']['created_at'] && \Carbon\Carbon::parse($detail['user']['created_at'])->addDays(8)->isFuture() ? 'Pending' : 'No') }}
                    </span>
                </li>
            </ul>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Platforms</h3>
            <ul class="space-y-2 text-sm">
                @forelse($detail['platforms'] as $p)
                <li class="flex justify-between p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <span class="capitalize">{{ $p['platform'] }}</span>
                    <span>{{ $p['sessions'] }} sessions · {{ $p['hours'] }}h</span>
                </li>
                @empty
                <li class="text-gray-500 text-center py-4">No sessions in period</li>
                @endforelse
            </ul>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Top actions</h3>
            <ul class="space-y-2 text-sm">
                @forelse($detail['top_actions'] as $a)
                <li class="flex justify-between p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <code class="text-indigo-600">{{ $a['action'] }}</code>
                    <span class="font-bold">{{ number_format($a['count']) }}</span>
                </li>
                @empty
                <li class="text-gray-500 text-center py-4">No actions tracked yet</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Feature screen views</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b dark:border-gray-700">
                        <th class="pb-2">Feature</th>
                        <th class="pb-2">Views</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($detail['feature_usage'] as $f)
                    <tr class="border-b dark:border-gray-700/50">
                        <td class="py-2 font-medium">{{ $f['label'] }}</td>
                        <td class="py-2">{{ number_format($f['views']) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="py-6 text-center text-gray-500">No feature views in period</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Recent events</h3>
        <div class="overflow-x-auto max-h-96 overflow-y-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-white dark:bg-gray-800">
                    <tr class="text-left text-gray-500 border-b dark:border-gray-700">
                        <th class="pb-2">Time</th>
                        <th class="pb-2">Event</th>
                        <th class="pb-2">Feature</th>
                        <th class="pb-2">Platform</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($detail['recent_events'] as $e)
                    <tr class="border-b dark:border-gray-700/50">
                        <td class="py-2 text-gray-500 whitespace-nowrap">
                            {{ $e['occurred_at'] ? \Carbon\Carbon::parse($e['occurred_at'])->format('M j H:i') : '—' }}
                        </td>
                        <td class="py-2">
                            @if($e['action_key'])
                                <code class="text-indigo-600">{{ $e['action_key'] }}</code>
                            @else
                                {{ $e['event_name'] }}
                            @endif
                        </td>
                        <td class="py-2">{{ $e['feature_key'] ?? '—' }}</td>
                        <td class="py-2 capitalize">{{ $e['platform'] ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="py-6 text-center text-gray-500">No events yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
