<?php

namespace App\Services;

use App\Models\ProductAnalyticsEvent;
use App\Models\ProductAnalyticsSession;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ProductAnalyticsIngestService
{
    public function __construct(
        private ?ProductAnalyticsBridgeService $bridge = null,
    ) {
        $this->bridge ??= app(ProductAnalyticsBridgeService::class);
    }

    public const FEATURES = [
        'chats' => 'Chats',
        'status' => 'Status',
        'channels' => 'Channels',
        'world' => 'World Feed',
        'mail' => 'Mail',
        'ai' => 'AI Assistant',
        'calls' => 'Calls',
        'live' => 'Live',
        'settings' => 'Settings',
        'wallet' => 'Sika Wallet',
        'contacts' => 'Contacts',
        'search' => 'Search',
        'notifications' => 'Notifications',
        'profile' => 'Profile',
        'groups' => 'Groups',
        'unknown' => 'Other',
    ];

    public function startSession(
        int $userId,
        string $sessionUuid,
        string $platform,
        ?string $appVersion = null,
        ?string $deviceType = null,
        ?string $osVersion = null,
        ?string $locale = null,
    ): ProductAnalyticsSession {
        $now = now();

        $session = ProductAnalyticsSession::updateOrCreate(
            ['session_uuid' => $sessionUuid],
            [
                'user_id' => $userId,
                'platform' => $this->normalizePlatform($platform),
                'app_version' => $appVersion,
                'device_type' => $deviceType,
                'os_version' => $osVersion,
                'locale' => $locale,
                'started_at' => $now,
                'last_heartbeat_at' => $now,
                'is_active' => true,
                'ended_at' => null,
            ]
        );

        $this->recordEvent($userId, $sessionUuid, 'session_start', null, null, [
            'platform' => $platform,
        ], $platform, $now);

        return $session;
    }

    public function heartbeat(string $sessionUuid, int $userId): void
    {
        $session = ProductAnalyticsSession::where('session_uuid', $sessionUuid)
            ->where('user_id', $userId)
            ->first();

        if (!$session || !$session->is_active) {
            return;
        }

        $now = now();
        $session->update([
            'last_heartbeat_at' => $now,
            'duration_seconds' => max(0, $session->started_at->diffInSeconds($now)),
        ]);
    }

    public function endSession(string $sessionUuid, int $userId, ?int $durationSeconds = null): void
    {
        $session = ProductAnalyticsSession::where('session_uuid', $sessionUuid)
            ->where('user_id', $userId)
            ->first();

        if (!$session) {
            return;
        }

        $now = now();
        $duration = $durationSeconds ?? max(0, $session->started_at->diffInSeconds($now));

        $session->update([
            'ended_at' => $now,
            'last_heartbeat_at' => $now,
            'duration_seconds' => $duration,
            'is_active' => false,
        ]);

        $this->recordEvent($userId, $sessionUuid, 'session_end', null, null, [
            'duration_seconds' => $duration,
        ], $session->platform, $now);
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    public function ingestBatch(int $userId, string $sessionUuid, string $platform, array $events): int
    {
        $count = 0;
        $session = ProductAnalyticsSession::where('session_uuid', $sessionUuid)->first();

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $eventName = (string) ($event['event_name'] ?? $event['name'] ?? '');
            if ($eventName === '') {
                continue;
            }

            $featureKey = $this->normalizeFeatureKey($event['feature_key'] ?? $event['feature'] ?? null);
            $actionKey = isset($event['action_key']) ? (string) $event['action_key'] : null;
            $properties = is_array($event['properties'] ?? null) ? $event['properties'] : [];
            $occurredAt = isset($event['occurred_at'])
                ? Carbon::parse($event['occurred_at'])
                : now();

            $this->recordEvent(
                $userId,
                $sessionUuid,
                $eventName,
                $featureKey,
                $actionKey,
                $properties,
                $platform,
                $occurredAt
            );

            if ($eventName === 'screen_view' && $session) {
                $session->increment('screen_views_count');
            }

            $count++;
        }

        if ($session) {
            $session->increment('events_count', $count);
            $session->update(['last_heartbeat_at' => now()]);
        }

        return $count;
    }

    public function closeStaleSessions(int $inactiveMinutes = 30): int
    {
        $cutoff = now()->subMinutes($inactiveMinutes);
        $stale = ProductAnalyticsSession::where('is_active', true)
            ->where(function ($q) use ($cutoff) {
                $q->where('last_heartbeat_at', '<', $cutoff)
                    ->orWhere(function ($q2) use ($cutoff) {
                        $q2->whereNull('last_heartbeat_at')
                            ->where('started_at', '<', $cutoff);
                    });
            })
            ->get();

        foreach ($stale as $session) {
            $endedAt = $session->last_heartbeat_at ?? $cutoff;
            $duration = max(0, $session->started_at->diffInSeconds($endedAt));
            $session->update([
                'ended_at' => $endedAt,
                'duration_seconds' => $duration,
                'is_active' => false,
            ]);
        }

        return $stale->count();
    }

    public function trackServerAction(
        int $userId,
        string $actionKey,
        ?string $featureKey = null,
        array $properties = [],
        string $platform = 'api',
    ): void {
        $this->recordEvent(
            $userId,
            null,
            'action',
            $featureKey,
            $actionKey,
            $properties,
            $platform,
            now(),
        );
    }

    private function recordEvent(
        int $userId,
        ?string $sessionUuid,
        string $eventName,
        ?string $featureKey,
        ?string $actionKey,
        array $properties,
        string $platform,
        Carbon $occurredAt,
    ): void {
        $event = ProductAnalyticsEvent::create([
            'session_uuid' => $sessionUuid,
            'user_id' => $userId,
            'event_name' => $eventName,
            'feature_key' => $featureKey,
            'action_key' => $actionKey,
            'properties' => $properties ?: null,
            'platform' => $this->normalizePlatform($platform),
            'occurred_at' => $occurredAt,
        ]);

        if ($this->bridge->isEnabled()) {
            $this->bridge->forward($event);
        }
    }

    public function normalizeFeatureKey(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        $key = strtolower(trim($key, '/'));
        $map = [
            'chat' => 'chats',
            '/chats' => 'chats',
            '/status' => 'status',
            '/channels' => 'channels',
            '/world' => 'world',
            '/mail' => 'mail',
            '/ai' => 'ai',
            '/calls' => 'calls',
            '/live-broadcast' => 'live',
            '/live' => 'live',
            '/settings' => 'settings',
            '/wallet' => 'wallet',
            '/sika' => 'wallet',
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        if (str_starts_with($key, '/')) {
            $key = ltrim($key, '/');
        }

        return array_key_exists($key, self::FEATURES) ? $key : 'unknown';
    }

    public function normalizePlatform(string $platform): string
    {
        $p = strtolower(trim($platform));

        return match (true) {
            str_contains($p, 'windows') => 'windows',
            str_contains($p, 'macos') || str_contains($p, 'darwin') => 'macos',
            str_contains($p, 'linux') => 'linux',
            str_contains($p, 'android') => 'android',
            str_contains($p, 'ios') || str_contains($p, 'iphone') => 'ios',
            str_contains($p, 'desktop') => 'desktop',
            str_contains($p, 'mobile') => 'mobile',
            str_contains($p, 'web') => 'web',
            default => $p !== '' ? $p : 'unknown',
        };
    }

    public static function newSessionUuid(): string
    {
        return (string) Str::uuid();
    }
}
