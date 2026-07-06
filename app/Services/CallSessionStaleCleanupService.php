<?php

namespace App\Services;

use App\Jobs\SendCallCancelNotification;
use App\Models\CallSession;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Ends call sessions that were never properly closed by clients (crash/kill/network loss).
 */
class CallSessionStaleCleanupService
{
    public function __construct(
        protected CallSessionEndService $callSessionEnd,
        protected LiveKitRoomAdminService $liveKitRoomAdmin,
    ) {}

    /**
     * @return array{ended: int, reasons: array<string, int>}
     */
    public function cleanup(): array
    {
        $reasons = [
            'timeout' => 0,
            'expired' => 0,
            'stale' => 0,
        ];

        $reasons['timeout'] += $this->endUnansweredRinging();
        $reasons['expired'] += $this->endExpiredLinks();
        $reasons['stale'] += $this->endBeyondMaxDuration();
        $reasons['stale'] += $this->endEmptyLiveKitRooms();

        $ended = array_sum($reasons);

        if ($ended > 0) {
            Log::info('Stale call session cleanup completed', [
                'ended' => $ended,
                'reasons' => $reasons,
            ]);
        }

        return ['ended' => $ended, 'reasons' => $reasons];
    }

    protected function endUnansweredRinging(): int
    {
        $cutoff = now()->subSeconds((int) config('calls.ringing_timeout_seconds', 90));
        $sessions = CallSession::query()
            ->whereIn('status', ['pending', 'calling'])
            ->whereNull('started_at')
            ->where('created_at', '<', $cutoff)
            ->get();

        return $this->finalizeEach($sessions, 'timeout');
    }

    protected function endExpiredLinks(): int
    {
        $sessions = CallSession::query()
            ->whereIn('status', ['pending', 'calling', 'ongoing'])
            ->get()
            ->filter(fn (CallSession $s) => $s->isLinkExpired());

        return $this->finalizeEach($sessions, 'expired');
    }

    protected function endBeyondMaxDuration(): int
    {
        $cutoff = now()->subHours((int) config('calls.max_ongoing_hours', 6));
        $sessions = CallSession::query()
            ->where('status', 'ongoing')
            ->whereNotNull('started_at')
            ->where('started_at', '<', $cutoff)
            ->get();

        return $this->finalizeEach($sessions, 'stale');
    }

    protected function endEmptyLiveKitRooms(): int
    {
        $grace = (int) config('calls.empty_room_grace_seconds', 120);
        $cutoff = now()->subSeconds($grace);

        $sessions = CallSession::query()
            ->where('status', 'ongoing')
            ->whereNotNull('started_at')
            ->where('started_at', '<', $cutoff)
            ->get();

        $ended = 0;
        foreach ($sessions as $session) {
            if ($session->status === 'ended') {
                continue;
            }

            $liveKitCount = $this->liveKitParticipantCount($session);
            if ($liveKitCount === null) {
                // LiveKit unreachable — skip; max-duration pass will eventually close.
                continue;
            }

            if ($liveKitCount > 0) {
                continue;
            }

            if ($this->finalizeSession($session, 'stale')) {
                $ended++;
            }
        }

        return $ended;
    }

    protected function liveKitParticipantCount(CallSession $session): ?int
    {
        try {
            return $this->liveKitRoomAdmin->participantCount('call_'.$session->id);
        } catch (\Throwable $e) {
            Log::debug('Stale cleanup: LiveKit participant count failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  iterable<CallSession>  $sessions
     */
    protected function finalizeEach(iterable $sessions, string $reason): int
    {
        $ended = 0;
        foreach ($sessions as $session) {
            if ($this->finalizeSession($session, $reason)) {
                $ended++;
            }
        }

        return $ended;
    }

    protected function finalizeSession(CallSession $session, string $reason): bool
    {
        $session->refresh();
        if ($session->status === 'ended') {
            return false;
        }

        $ok = $this->callSessionEnd->finalize($session, null, $reason);
        if (! $ok) {
            return false;
        }

        $this->deleteLiveKitRoom($session);
        $this->dismissNativeCalls($session);

        return true;
    }

    protected function deleteLiveKitRoom(CallSession $session): void
    {
        try {
            $this->liveKitRoomAdmin->deleteRoom('call_'.$session->id);
        } catch (\Throwable $e) {
            Log::debug('Stale cleanup: LiveKit room delete skipped', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function dismissNativeCalls(CallSession $session): void
    {
        foreach ($this->participantUserIds($session) as $userId) {
            $user = User::find($userId);
            if ($user) {
                SendCallCancelNotification::dispatch($user, $session)->afterResponse();
            }
        }
    }

    /**
     * @return list<int>
     */
    protected function participantUserIds(CallSession $session): array
    {
        $ids = array_filter([
            $session->caller_id ? (int) $session->caller_id : null,
            $session->callee_id ? (int) $session->callee_id : null,
        ]);

        $participantIds = $session->participants()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique([...$ids, ...$participantIds]));
    }
}
