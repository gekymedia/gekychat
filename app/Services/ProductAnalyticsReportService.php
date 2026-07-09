<?php

namespace App\Services;

use App\Models\ProductAnalyticsDailyRollup;
use App\Models\ProductAnalyticsEvent;
use App\Models\ProductAnalyticsSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductAnalyticsReportService
{
    public function __construct(
        private ProductAnalyticsIngestService $ingest
    ) {}

    public function overview(string $period = '7d'): array
    {
        [$start, $end] = $this->periodRange($period);
        $prevStart = $start->copy()->subDays($start->diffInDays($end) + 1);
        $prevEnd = $start->copy()->subSecond();

        $sessions = ProductAnalyticsSession::whereBetween('started_at', [$start, $end]);
        $prevSessions = ProductAnalyticsSession::whereBetween('started_at', [$prevStart, $prevEnd]);

        $totalSessions = (clone $sessions)->count();
        $totalHours = round((clone $sessions)->sum('duration_seconds') / 3600, 1);
        $avgSessionMinutes = round((clone $sessions)->avg('duration_seconds') / 60, 1);
        $uniqueUsers = (clone $sessions)->distinct('user_id')->count('user_id');
        $eventsCount = ProductAnalyticsEvent::whereBetween('occurred_at', [$start, $end])->count();

        $dau = User::where('last_seen_at', '>=', Carbon::today())->count();
        $wau = User::where('last_seen_at', '>=', Carbon::now()->subDays(7))->count();
        $mau = User::where('last_seen_at', '>=', Carbon::now()->subDays(30))->count();

        $activeNow = ProductAnalyticsSession::where('is_active', true)
            ->where('last_heartbeat_at', '>=', now()->subMinutes(5))
            ->count();

        $prevSessionCount = (clone $prevSessions)->count();
        $sessionGrowth = $prevSessionCount > 0
            ? round((($totalSessions - $prevSessionCount) / $prevSessionCount) * 100, 1)
            : 0;

        return [
            'period' => $period,
            'range' => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
            'total_sessions' => $totalSessions,
            'session_growth_pct' => $sessionGrowth,
            'total_hours_in_app' => $totalHours,
            'avg_session_minutes' => $avgSessionMinutes ?: 0,
            'unique_active_users' => $uniqueUsers,
            'total_events' => $eventsCount,
            'dau' => $dau,
            'wau' => $wau,
            'mau' => $mau,
            'stickiness_pct' => $mau > 0 ? round(($dau / $mau) * 100, 1) : 0,
            'active_sessions_now' => $activeNow,
        ];
    }

    public function featureUsage(string $period = '7d'): array
    {
        [$start, $end] = $this->periodRange($period);

        $screenViews = ProductAnalyticsEvent::query()
            ->where('event_name', 'screen_view')
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotNull('feature_key')
            ->select('feature_key', DB::raw('COUNT(*) as views'), DB::raw('COUNT(DISTINCT user_id) as unique_users'))
            ->groupBy('feature_key')
            ->orderByDesc('views')
            ->get();

        $timeSpent = ProductAnalyticsEvent::query()
            ->where('event_name', 'screen_view')
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotNull('feature_key')
            ->select(
                'feature_key',
                DB::raw("COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(properties, '$.duration_seconds')) AS UNSIGNED)), 0) as seconds")
            )
            ->groupBy('feature_key')
            ->orderByDesc('seconds')
            ->get()
            ->keyBy('feature_key');

        $features = [];
        foreach ($screenViews as $row) {
            $key = $row->feature_key;
            $seconds = (int) ($timeSpent[$key]->seconds ?? 0);
            $features[] = [
                'feature_key' => $key,
                'label' => ProductAnalyticsIngestService::FEATURES[$key] ?? ucfirst($key),
                'views' => (int) $row->views,
                'unique_users' => (int) $row->unique_users,
                'hours' => round($seconds / 3600, 2),
                'minutes' => round($seconds / 60, 1),
            ];
        }

        usort($features, fn ($a, $b) => $b['minutes'] <=> $a['minutes']);

        return $features;
    }

    public function platformBreakdown(string $period = '7d'): array
    {
        [$start, $end] = $this->periodRange($period);

        return ProductAnalyticsSession::whereBetween('started_at', [$start, $end])
            ->select(
                'platform',
                DB::raw('COUNT(*) as sessions'),
                DB::raw('COUNT(DISTINCT user_id) as users'),
                DB::raw('SUM(duration_seconds) as total_seconds')
            )
            ->groupBy('platform')
            ->orderByDesc('sessions')
            ->get()
            ->map(fn ($r) => [
                'platform' => $r->platform,
                'sessions' => (int) $r->sessions,
                'users' => (int) $r->users,
                'hours' => round(((int) $r->total_seconds) / 3600, 1),
            ])
            ->values()
            ->all();
    }

    public function sessionsOverTime(string $period = '7d'): array
    {
        [$start, $end] = $this->periodRange($period);

        $rows = ProductAnalyticsSession::whereBetween('started_at', [$start, $end])
            ->select(
                DB::raw('DATE(started_at) as day'),
                DB::raw('COUNT(*) as sessions'),
                DB::raw('COUNT(DISTINCT user_id) as users'),
                DB::raw('SUM(duration_seconds) as seconds')
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return $rows->map(fn ($r) => [
            'date' => $r->day,
            'sessions' => (int) $r->sessions,
            'users' => (int) $r->users,
            'hours' => round(((int) $r->seconds) / 3600, 1),
        ])->values()->all();
    }

    /**
     * Signup → first message → day-7 return funnel for recent cohorts.
     */
    public function acquisitionFunnel(int $days = 30): array
    {
        $start = now()->subDays($days)->startOfDay();
        $end = now();

        $signups = User::whereBetween('created_at', [$start, $end])->get(['id', 'created_at']);
        $total = $signups->count();

        if ($total === 0) {
            return [
                'days' => $days,
                'range' => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
                'total_signups' => 0,
                'steps' => $this->emptyFunnelSteps(),
            ];
        }

        $userIds = $signups->pluck('id');

        $messagedDirect = DB::table('messages')
            ->whereIn('sender_id', $userIds)
            ->distinct()
            ->pluck('sender_id');

        $messagedGroup = DB::table('group_messages')
            ->whereIn('sender_id', $userIds)
            ->distinct()
            ->pluck('sender_id');

        $messagedIds = $messagedDirect->merge($messagedGroup)->unique()->values();
        $messagedCount = $messagedIds->count();

        $day7Returned = $this->countDay7Returns($signups);

        $steps = [
            [
                'key' => 'signup',
                'label' => 'Signed up',
                'count' => $total,
                'pct_of_signup' => 100.0,
                'pct_of_previous' => 100.0,
            ],
            [
                'key' => 'first_message',
                'label' => 'Sent first message',
                'count' => $messagedCount,
                'pct_of_signup' => round(($messagedCount / $total) * 100, 1),
                'pct_of_previous' => round(($messagedCount / $total) * 100, 1),
            ],
            [
                'key' => 'day_7_return',
                'label' => 'Returned on day 7',
                'count' => $day7Returned,
                'pct_of_signup' => round(($day7Returned / $total) * 100, 1),
                'pct_of_previous' => $messagedCount > 0
                    ? round(($day7Returned / $messagedCount) * 100, 1)
                    : 0.0,
            ],
        ];

        return [
            'days' => $days,
            'range' => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
            'total_signups' => $total,
            'steps' => $steps,
        ];
    }

    public function searchUsers(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $users = User::query()
            ->when(is_numeric($query), fn ($q) => $q->where('id', (int) $query))
            ->when(!is_numeric($query), function ($q) use ($query) {
                $q->where(function ($inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%");
                });
            })
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->get(['id', 'name', 'phone', 'email', 'created_at', 'last_seen_at']);

        return $users->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'phone' => $u->phone,
            'email' => $u->email,
            'created_at' => $u->created_at?->toIso8601String(),
            'last_seen_at' => $u->last_seen_at?->toIso8601String(),
        ])->all();
    }

    public function userDetail(int $userId, string $period = '30d'): array
    {
        $user = User::findOrFail($userId);
        [$start, $end] = $this->periodRange($period);

        $sessions = ProductAnalyticsSession::where('user_id', $userId)
            ->whereBetween('started_at', [$start, $end]);

        $sessionCount = (clone $sessions)->count();
        $totalSeconds = (int) (clone $sessions)->sum('duration_seconds');

        $platforms = (clone $sessions)
            ->select('platform', DB::raw('COUNT(*) as sessions'), DB::raw('SUM(duration_seconds) as seconds'))
            ->groupBy('platform')
            ->get()
            ->map(fn ($r) => [
                'platform' => $r->platform,
                'sessions' => (int) $r->sessions,
                'hours' => round(((int) $r->seconds) / 3600, 2),
            ])
            ->all();

        $featureUsage = ProductAnalyticsEvent::query()
            ->where('user_id', $userId)
            ->where('event_name', 'screen_view')
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotNull('feature_key')
            ->select('feature_key', DB::raw('COUNT(*) as views'))
            ->groupBy('feature_key')
            ->orderByDesc('views')
            ->get()
            ->map(fn ($r) => [
                'feature_key' => $r->feature_key,
                'label' => ProductAnalyticsIngestService::FEATURES[$r->feature_key] ?? $r->feature_key,
                'views' => (int) $r->views,
            ])
            ->all();

        $topActions = ProductAnalyticsEvent::where('user_id', $userId)
            ->where('event_name', 'action')
            ->whereBetween('occurred_at', [$start, $end])
            ->select('action_key', DB::raw('COUNT(*) as count'))
            ->groupBy('action_key')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['action' => $r->action_key, 'count' => (int) $r->count])
            ->all();

        $recentEvents = ProductAnalyticsEvent::where('user_id', $userId)
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get()
            ->map(fn (ProductAnalyticsEvent $e) => [
                'event_name' => $e->event_name,
                'action_key' => $e->action_key,
                'feature_key' => $e->feature_key,
                'platform' => $e->platform,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
                'properties' => $e->properties,
            ])
            ->all();

        $firstMessageAt = $this->firstMessageAt($userId);
        $day7Return = $this->userReturnedDay7($user);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
                'last_seen_at' => $user->last_seen_at?->toIso8601String(),
            ],
            'period' => $period,
            'range' => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
            'sessions' => $sessionCount,
            'hours_in_app' => round($totalSeconds / 3600, 2),
            'platforms' => $platforms,
            'feature_usage' => $featureUsage,
            'top_actions' => $topActions,
            'recent_events' => $recentEvents,
            'milestones' => [
                'first_message_at' => $firstMessageAt?->toIso8601String(),
                'returned_day_7' => $day7Return,
                'sent_first_message' => $firstMessageAt !== null,
            ],
        ];
    }

    public function exportRows(string $type, string $period = '7d'): array
    {
        return match ($type) {
            'features' => $this->exportFeatureRows($period),
            'sessions' => $this->exportSessionRows($period),
            'actions' => $this->exportActionRows($period),
            'funnel' => $this->exportFunnelRows(),
            default => [],
        };
    }

    private function exportFeatureRows(string $period): array
    {
        $rows = [['Feature', 'Screen views', 'Unique users', 'Minutes', 'Hours']];
        foreach ($this->featureUsage($period) as $f) {
            $rows[] = [$f['label'], $f['views'], $f['unique_users'], $f['minutes'], $f['hours']];
        }

        return $rows;
    }

    private function exportSessionRows(string $period): array
    {
        $rows = [['Date', 'Sessions', 'Users', 'Hours']];
        foreach ($this->sessionsOverTime($period) as $d) {
            $rows[] = [$d['date'], $d['sessions'], $d['users'], $d['hours']];
        }

        return $rows;
    }

    private function exportActionRows(string $period): array
    {
        $rows = [['Action', 'Feature', 'Count']];
        foreach ($this->topActions($period, 100) as $a) {
            $rows[] = [$a['action'], $a['feature'] ?? '', $a['count']];
        }

        return $rows;
    }

    private function exportFunnelRows(): array
    {
        $funnel = $this->acquisitionFunnel(30);
        $rows = [['Step', 'Users', '% of signups', '% of previous step']];
        foreach ($funnel['steps'] as $step) {
            $rows[] = [
                $step['label'],
                $step['count'],
                $step['pct_of_signup'],
                $step['pct_of_previous'],
            ];
        }

        return $rows;
    }

    private function emptyFunnelSteps(): array
    {
        return [
            ['key' => 'signup', 'label' => 'Signed up', 'count' => 0, 'pct_of_signup' => 0, 'pct_of_previous' => 0],
            ['key' => 'first_message', 'label' => 'Sent first message', 'count' => 0, 'pct_of_signup' => 0, 'pct_of_previous' => 0],
            ['key' => 'day_7_return', 'label' => 'Returned on day 7', 'count' => 0, 'pct_of_signup' => 0, 'pct_of_previous' => 0],
        ];
    }

    private function countDay7Returns(Collection $signups): int
    {
        $count = 0;
        foreach ($signups as $user) {
            if ($this->userReturnedDay7($user)) {
                $count++;
            }
        }

        return $count;
    }

    private function userReturnedDay7(User $user): bool
    {
        if (!$user->created_at) {
            return false;
        }

        $signup = Carbon::parse($user->created_at);
        $windowStart = $signup->copy()->addDays(6)->startOfDay();
        $windowEnd = $signup->copy()->addDays(8)->endOfDay();

        if ($windowEnd->isFuture()) {
            return false;
        }

        if ($user->last_seen_at && $user->last_seen_at->between($windowStart, $windowEnd)) {
            return true;
        }

        if (ProductAnalyticsSession::where('user_id', $user->id)
            ->whereBetween('started_at', [$windowStart, $windowEnd])
            ->exists()) {
            return true;
        }

        return ProductAnalyticsEvent::where('user_id', $user->id)
            ->whereBetween('occurred_at', [$windowStart, $windowEnd])
            ->exists();
    }

    private function firstMessageAt(int $userId): ?Carbon
    {
        $direct = DB::table('messages')
            ->where('sender_id', $userId)
            ->min('created_at');

        $group = DB::table('group_messages')
            ->where('sender_id', $userId)
            ->min('created_at');

        $dates = array_filter([$direct, $group]);
        if ($dates === []) {
            return null;
        }

        return Carbon::parse(min($dates));
    }

    public function topActions(string $period = '7d', int $limit = 15): array
    {
        [$start, $end] = $this->periodRange($period);

        return ProductAnalyticsEvent::where('event_name', 'action')
            ->whereBetween('occurred_at', [$start, $end])
            ->select('action_key', 'feature_key', DB::raw('COUNT(*) as count'))
            ->groupBy('action_key', 'feature_key')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'action' => $r->action_key ?? 'unknown',
                'feature' => $r->feature_key,
                'count' => (int) $r->count,
            ])
            ->all();
    }

    public function retentionCohorts(int $weeks = 8): array
    {
        $cohorts = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekStart = Carbon::now()->subWeeks($i)->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();

            $registered = User::whereBetween('created_at', [$weekStart, $weekEnd])->pluck('id');
            $cohortSize = $registered->count();
            if ($cohortSize === 0) {
                continue;
            }

            $weekData = ['cohort' => $weekStart->format('M d'), 'size' => $cohortSize, 'weeks' => []];

            for ($w = 0; $w <= min(4, $i); $w++) {
                $activeStart = $weekStart->copy()->addWeeks($w);
                $activeEnd = $activeStart->copy()->endOfWeek();
                $active = ProductAnalyticsSession::whereIn('user_id', $registered)
                    ->whereBetween('started_at', [$activeStart, $activeEnd])
                    ->distinct('user_id')
                    ->count('user_id');
                $weekData['weeks'][] = [
                    'week' => $w,
                    'pct' => round(($active / $cohortSize) * 100, 1),
                    'users' => $active,
                ];
            }

            $cohorts[] = $weekData;
        }

        return $cohorts;
    }

    public function sessionDurationBuckets(string $period = '7d'): array
    {
        [$start, $end] = $this->periodRange($period);

        $sessions = ProductAnalyticsSession::whereBetween('started_at', [$start, $end])
            ->where('duration_seconds', '>', 0)
            ->pluck('duration_seconds');

        $buckets = [
            '< 1 min' => 0,
            '1–5 min' => 0,
            '5–15 min' => 0,
            '15–30 min' => 0,
            '30–60 min' => 0,
            '60+ min' => 0,
        ];

        foreach ($sessions as $sec) {
            $min = $sec / 60;
            match (true) {
                $min < 1 => $buckets['< 1 min']++,
                $min < 5 => $buckets['1–5 min']++,
                $min < 15 => $buckets['5–15 min']++,
                $min < 30 => $buckets['15–30 min']++,
                $min < 60 => $buckets['30–60 min']++,
                default => $buckets['60+ min']++,
            };
        }

        return collect($buckets)->map(fn ($count, $label) => ['label' => $label, 'count' => $count])->values()->all();
    }

    public function realtime(): array
    {
        $fiveMin = now()->subMinutes(5);

        return [
            'active_sessions' => ProductAnalyticsSession::where('is_active', true)
                ->where('last_heartbeat_at', '>=', $fiveMin)->count(),
            'events_last_5m' => ProductAnalyticsEvent::where('occurred_at', '>=', $fiveMin)->count(),
            'features_now' => ProductAnalyticsEvent::where('event_name', 'screen_view')
                ->where('occurred_at', '>=', $fiveMin)
                ->whereNotNull('feature_key')
                ->select('feature_key', DB::raw('COUNT(*) as c'))
                ->groupBy('feature_key')
                ->orderByDesc('c')
                ->limit(8)
                ->get()
                ->map(fn ($r) => [
                    'feature' => $r->feature_key,
                    'label' => ProductAnalyticsIngestService::FEATURES[$r->feature_key] ?? $r->feature_key,
                    'count' => (int) $r->c,
                ])
                ->all(),
        ];
    }

    public function buildDailyRollups(Carbon $date): void
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $sessions = ProductAnalyticsSession::whereBetween('started_at', [$start, $end]);
        $this->upsertRollup($date, 'sessions', 'all', (clone $sessions)->count());
        $this->upsertRollup($date, 'hours_in_app', 'all', round((clone $sessions)->sum('duration_seconds') / 3600, 4));
        $this->upsertRollup($date, 'unique_users', 'all', (clone $sessions)->distinct('user_id')->count('user_id'));

        foreach ($this->featureUsageForDay($start, $end) as $feature => $data) {
            $this->upsertRollup($date, 'feature_views', $feature, $data['views']);
            $this->upsertRollup($date, 'feature_minutes', $feature, $data['minutes']);
        }
    }

    private function featureUsageForDay(Carbon $start, Carbon $end): array
    {
        $out = [];
        $rows = ProductAnalyticsEvent::where('event_name', 'screen_view')
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotNull('feature_key')
            ->select('feature_key', DB::raw('COUNT(*) as views'))
            ->groupBy('feature_key')
            ->get();

        foreach ($rows as $row) {
            $out[$row->feature_key] = ['views' => (int) $row->views, 'minutes' => 0];
        }

        return $out;
    }

    private function upsertRollup(Carbon $date, string $metric, string $dimension, float $value): void
    {
        ProductAnalyticsDailyRollup::updateOrCreate(
            [
                'rollup_date' => $date->toDateString(),
                'metric_key' => $metric,
                'dimension' => $dimension,
            ],
            ['value' => $value]
        );
    }

 
    private function periodRange(string $period): array
    {
        $end = now();
        $start = match ($period) {
            '24h', '1d' => $end->copy()->subDay(),
            '30d' => $end->copy()->subDays(30),
            '90d' => $end->copy()->subDays(90),
            default => $end->copy()->subDays(7),
        };

        return [$start, $end];
    }
}
