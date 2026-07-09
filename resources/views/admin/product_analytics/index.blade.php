@extends('layouts.admin')

@section('title', 'Product Analytics')

@section('content')
<div class="space-y-6" id="product-analytics-root" data-period="{{ $period }}">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Product Analytics</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Feature usage, session time, retention — owner-level insights</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @foreach(['24h' => '24h', '7d' => '7 days', '30d' => '30 days', '90d' => '90 days'] as $key => $label)
                <a href="{{ route('admin.product-analytics.index', ['period' => $key]) }}"
                   class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $period === $key ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200' }}">
                    {{ $label }}
                </a>
            @endforeach
            <span class="text-gray-300 dark:text-gray-600">|</span>
            @foreach(['features' => 'Features', 'sessions' => 'Sessions', 'actions' => 'Actions', 'funnel' => 'Funnel'] as $exportType => $exportLabel)
                <a href="{{ route('admin.product-analytics.export', ['type' => $exportType, 'period' => $period]) }}"
                   class="px-3 py-1.5 rounded-lg text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-700">
                    CSV {{ $exportLabel }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- User drill-down search --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow flex flex-col md:flex-row gap-3 md:items-end">
        <form method="get" action="{{ route('admin.product-analytics.index') }}" class="flex flex-1 gap-2 items-end flex-wrap">
            <input type="hidden" name="period" value="{{ $period }}">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-500 mb-1">Per-user drill-down</label>
                <input type="search" name="q" value="{{ $searchQuery }}" placeholder="User ID, name, phone, or email"
                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
            </div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Search</button>
        </form>
        @if($bridgeEnabled)
            <span class="text-xs text-emerald-600 dark:text-emerald-400">Amplitude / Firebase bridge active</span>
        @else
            <span class="text-xs text-gray-500">Bridge off — set AMPLITUDE_API_KEY or FIREBASE_GA4_* in .env</span>
        @endif
    </div>
    @if(!empty($searchResults))
    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Search results</h3>
        <div class="space-y-2">
            @foreach($searchResults as $u)
            <a href="{{ route('admin.product-analytics.user', $u['id']) }}"
               class="flex justify-between items-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                <span class="text-sm text-gray-900 dark:text-white">
                    <strong>#{{ $u['id'] }}</strong> {{ $u['name'] ?? '—' }}
                    <span class="text-gray-500">{{ $u['phone'] }}</span>
                </span>
                <span class="text-xs text-gray-500">Last seen {{ $u['last_seen_at'] ? \Carbon\Carbon::parse($u['last_seen_at'])->diffForHumans() : 'never' }}</span>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Live strip --}}
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl p-4 text-white flex flex-wrap gap-6 items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 bg-green-300 rounded-full animate-pulse"></span>
            <span class="font-semibold">Live now</span>
        </div>
        <div class="text-sm"><strong>{{ $realtime['active_sessions'] }}</strong> active sessions</div>
        <div class="text-sm"><strong>{{ $realtime['events_last_5m'] }}</strong> events (5m)</div>
        <div class="text-sm flex gap-2 flex-wrap">
            @foreach($realtime['features_now'] as $f)
                <span class="bg-white/20 px-2 py-0.5 rounded">{{ $f['label'] }}: {{ $f['count'] }}</span>
            @endforeach
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow hover-lift">
            <div class="text-xs text-gray-500 uppercase tracking-wide">DAU / MAU</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $overview['dau'] }} / {{ $overview['mau'] }}</div>
            <div class="text-xs text-indigo-600 mt-1">{{ $overview['stickiness_pct'] }}% stickiness</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow hover-lift">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Hours in app</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($overview['total_hours_in_app'], 1) }}</div>
            <div class="text-xs text-gray-500 mt-1">period total</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow hover-lift">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Avg session</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $overview['avg_session_minutes'] }}m</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow hover-lift">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Sessions</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($overview['total_sessions']) }}</div>
            <div class="text-xs {{ $overview['session_growth_pct'] >= 0 ? 'text-green-600' : 'text-red-600' }} mt-1">
                {{ $overview['session_growth_pct'] >= 0 ? '+' : '' }}{{ $overview['session_growth_pct'] }}% vs prior
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow hover-lift">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Unique users</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($overview['unique_active_users']) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow hover-lift">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Events tracked</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($overview['total_events']) }}</div>
        </div>
    </div>

    {{-- Acquisition funnel --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-semibold text-gray-900 dark:text-white">Acquisition funnel</h3>
                <p class="text-sm text-gray-500 mt-1">Last {{ $funnel['days'] }} days — signup → first message → day-7 return</p>
            </div>
            <span class="text-sm text-gray-500">{{ number_format($funnel['total_signups']) }} signups</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($funnel['steps'] as $i => $step)
            <div class="relative rounded-xl border dark:border-gray-700 p-4 {{ $i === 0 ? 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-200 dark:border-indigo-800' : 'bg-gray-50 dark:bg-gray-700/30' }}">
                <div class="text-xs uppercase tracking-wide text-gray-500">Step {{ $i + 1 }}</div>
                <div class="text-lg font-bold text-gray-900 dark:text-white mt-1">{{ $step['label'] }}</div>
                <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400 mt-2">{{ number_format($step['count']) }}</div>
                <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ $step['pct_of_signup'] }}% of signups
                    @if($i > 0)
                        · {{ $step['pct_of_previous'] }}% of previous step
                    @endif
                </div>
                <div class="mt-3 h-2 bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden">
                    <div class="h-full bg-indigo-500 rounded-full" style="width: {{ min(100, $step['pct_of_signup']) }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Sessions & hours over time</h3>
            <canvas id="sessionsChart" height="200"></canvas>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Time by feature (minutes)</h3>
            <canvas id="featureTimeChart" height="200"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow xl:col-span-2">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Feature usage ranking</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b dark:border-gray-700">
                            <th class="pb-2">Feature</th>
                            <th class="pb-2">Screen views</th>
                            <th class="pb-2">Unique users</th>
                            <th class="pb-2">Time (min)</th>
                            <th class="pb-2">Time (hrs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($featureUsage as $f)
                        <tr class="border-b dark:border-gray-700/50">
                            <td class="py-3 font-medium text-gray-900 dark:text-white">{{ $f['label'] }}</td>
                            <td class="py-3">{{ number_format($f['views']) }}</td>
                            <td class="py-3">{{ number_format($f['unique_users']) }}</td>
                            <td class="py-3">{{ number_format($f['minutes'], 1) }}</td>
                            <td class="py-3">{{ number_format($f['hours'], 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="py-8 text-center text-gray-500">No feature data yet — clients will populate after users open the app.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Platform breakdown</h3>
            <canvas id="platformChart" height="220"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Session duration distribution</h3>
            <canvas id="durationChart" height="180"></canvas>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Top user actions</h3>
            <ul class="space-y-2">
                @forelse($topActions as $a)
                <li class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <span class="text-sm"><code class="text-indigo-600">{{ $a['action'] }}</code>
                        @if($a['feature'])<span class="text-gray-500">in {{ $a['feature'] }}</span>@endif
                    </span>
                    <span class="font-bold text-gray-900 dark:text-white">{{ number_format($a['count']) }}</span>
                </li>
                @empty
                <li class="text-gray-500 text-sm py-4 text-center">No action events yet</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Weekly retention cohorts</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="pb-2 pr-4">Cohort</th>
                        <th class="pb-2 pr-4">Size</th>
                        @for($w = 0; $w <= 4; $w++)
                        <th class="pb-2 pr-4">W{{ $w }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @foreach($retention as $c)
                    <tr class="border-t dark:border-gray-700">
                        <td class="py-2 font-medium">{{ $c['cohort'] }}</td>
                        <td class="py-2">{{ $c['size'] }}</td>
                        @for($w = 0; $w <= 4; $w++)
                        <td class="py-2">
                            @php $cell = collect($c['weeks'])->firstWhere('week', $w); @endphp
                            @if($cell)
                                <span class="inline-block px-2 py-0.5 rounded text-xs font-medium"
                                      style="background: rgba(99,102,241,{{ min($cell['pct']/100, 1) }}); color: {{ $cell['pct'] > 50 ? '#fff' : '#1f2937' }}">
                                    {{ $cell['pct'] }}%
                                </span>
                            @else — @endif
                        </td>
                        @endfor
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const isDark = document.documentElement.classList.contains('dark');
    const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.08)';
    const textColor = isDark ? '#9ca3af' : '#6b7280';

    const sessionsData = @json($sessionsOverTime);
    const featureData = @json($featureUsage);
    const platformData = @json($platformBreakdown);
    const durationData = @json($durationBuckets);

    new Chart(document.getElementById('sessionsChart'), {
        type: 'line',
        data: {
            labels: sessionsData.map(d => d.date),
            datasets: [
                { label: 'Sessions', data: sessionsData.map(d => d.sessions), borderColor: '#6366f1', tension: 0.3, yAxisID: 'y' },
                { label: 'Hours', data: sessionsData.map(d => d.hours), borderColor: '#10b981', tension: 0.3, yAxisID: 'y1' },
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: { position: 'left', grid: { color: gridColor }, ticks: { color: textColor } },
                y1: { position: 'right', grid: { drawOnChartArea: false }, ticks: { color: textColor } },
                x: { grid: { color: gridColor }, ticks: { color: textColor } }
            }
        }
    });

    new Chart(document.getElementById('featureTimeChart'), {
        type: 'bar',
        data: {
            labels: featureData.map(f => f.label),
            datasets: [{ label: 'Minutes', data: featureData.map(f => f.minutes), backgroundColor: '#8b5cf6' }]
        },
        options: { indexAxis: 'y', scales: { x: { grid: { color: gridColor }, ticks: { color: textColor } }, y: { ticks: { color: textColor } } } }
    });

    new Chart(document.getElementById('platformChart'), {
        type: 'doughnut',
        data: {
            labels: platformData.map(p => p.platform),
            datasets: [{ data: platformData.map(p => p.sessions), backgroundColor: ['#6366f1','#10b981','#f59e0b','#ef4444','#3b82f6','#8b5cf6'] }]
        },
        options: { plugins: { legend: { labels: { color: textColor } } } }
    });

    new Chart(document.getElementById('durationChart'), {
        type: 'bar',
        data: {
            labels: durationData.map(d => d.label),
            datasets: [{ label: 'Sessions', data: durationData.map(d => d.count), backgroundColor: '#06b6d4' }]
        },
        options: { scales: { y: { grid: { color: gridColor }, ticks: { color: textColor } }, x: { ticks: { color: textColor } } } }
    });

    setInterval(async () => {
        try {
            const res = await fetch(@json(route('admin.product-analytics.api.realtime')));
            const rt = await res.json();
            // optional: update live strip via DOM
        } catch (_) {}
    }, 60000);
});
</script>
@endpush
@endsection
