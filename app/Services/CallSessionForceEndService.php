<?php

namespace App\Services;

use App\Jobs\SendCallCancelNotification;
use App\Models\CallSession;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Force-end an active call session: DB, signaling, LiveKit room, push, and call logs.
 */
class CallSessionForceEndService
{
    public function __construct(
        protected LiveKitRoomAdminService $liveKitRoomAdmin,
        protected CallSessionEndService $callSessionEnd,
    ) {}

    /**
     * End a call for all participants (admin force-end or internal cleanup).
     */
    public function forceEnd(CallSession $session, ?User $actor = null, string $reason = 'admin'): void
    {
        if ($session->status === 'ended') {
            return;
        }

        $this->callSessionEnd->finalize($session, $actor, $reason === 'admin' ? 'admin' : 'normal');

        $roomName = 'call_'.$session->id;
        try {
            $this->liveKitRoomAdmin->deleteRoom($roomName);
        } catch (\Throwable $e) {
            Log::warning('Force-end: LiveKit room delete failed', [
                'session_id' => $session->id,
                'room' => $roomName,
                'error' => $e->getMessage(),
            ]);
        }

        foreach ($this->participantUserIds($session) as $userId) {
            if ($actor !== null && (int) $actor->id === $userId) {
                continue;
            }
            $user = User::find($userId);
            if ($user) {
                SendCallCancelNotification::dispatch($user, $session)->afterResponse();
            }
        }

        Log::info('Call session force-ended', [
            'session_id' => $session->id,
            'actor_id' => $actor?->id,
            'reason' => $reason,
        ]);
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
            ->whereIn('status', ['invited', 'joined'])
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique([...$ids, ...$participantIds]));
    }
}
