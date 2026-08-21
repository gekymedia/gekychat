<?php

namespace App\Services;

use App\Events\CallSignal;
use App\Models\CallSession;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\Message;
use App\Models\User;
use App\Services\RealtimeDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Ends a call session, writes chat call-log rows, and notifies participants.
 *
 * Used by API end/decline, stale-session cleanup, and admin force-end.
 */
class CallSessionEndService
{
    private const ALLOWED_REASONS = [
        'declined',
        'no_answer',
        'normal',
        'timeout',
        'stale',
        'expired',
        'admin',
    ];

    /**
     * @param  'declined'|'no_answer'|'normal'|'timeout'|'stale'|'expired'|'admin'  $endReason
     * @return bool True when the session was transitioned to ended.
     */
    public function finalize(CallSession $session, ?User $endingUser, string $endReason): bool
    {
        if ($session->status === 'ended') {
            return false;
        }

        if (! in_array($endReason, self::ALLOWED_REASONS, true)) {
            $endReason = 'normal';
        }

        $duration = null;
        $startTime = $session->started_at ?? $session->created_at;
        if ($startTime) {
            $duration = (int) round($startTime->diffInSeconds(now()));
        }

        $session->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        $session->activeParticipants()->update([
            'status' => 'left',
            'left_at' => now(),
        ]);

        $isDeclined = $endReason === 'declined';
        $isMissed = in_array($endReason, ['no_answer', 'timeout'], true)
            || (! $isDeclined
                && (! $session->started_at || ($duration !== null && $duration < 2)));

        // Ring time before no-answer is not a connected call — omit from call log.
        $logDuration = ($isMissed || $isDeclined) ? null : $duration;

        try {
            $patchedExisting = $this->patchActiveCallInviteMessages(
                $session,
                $logDuration,
                $isMissed,
                $isDeclined
            );

            if ($session->group_id) {
                $group = Group::find($session->group_id);
                if ($group && ! $patchedExisting) {
                    $callData = [
                        'type' => $session->type,
                        'caller_id' => $session->caller_id,
                        'session_id' => $session->id,
                        'status' => 'ended',
                        'duration' => $logDuration,
                        'missed' => $isMissed,
                        'is_missed' => $isMissed,
                        'declined' => $isDeclined,
                    ];
                    $body = $this->callEndMessageBody($session->type, $logDuration, $isMissed, $isDeclined, false);

                    $groupMessage = GroupMessage::create([
                        'group_id' => $session->group_id,
                        'sender_id' => $session->caller_id,
                        'body' => $body,
                        'call_data' => $callData,
                        'is_encrypted' => false,
                    ]);

                    $groupMessage->load(['sender', 'attachments', 'reactions.user']);
                    RealtimeDispatcher::groupMessageSent($groupMessage);
                }
            } elseif ($session->caller_id && $session->callee_id) {
                $conversation = Conversation::findOrCreateDirect($session->caller_id, $session->callee_id);

                if (! $patchedExisting) {
                    $callData = [
                        'type' => $session->type,
                        'caller_id' => $session->caller_id,
                        'callee_id' => $session->callee_id,
                        'session_id' => $session->id,
                        'status' => 'ended',
                        'duration' => $logDuration,
                        'ended_at' => now()->toISOString(),
                        'missed' => $isMissed,
                        'is_missed' => $isMissed,
                        'declined' => $isDeclined,
                    ];

                    $body = $this->callEndMessageBody($session->type, $logDuration, $isMissed, $isDeclined, true);

                    $message = Message::create([
                        'conversation_id' => $conversation->id,
                        'sender_id' => $session->caller_id,
                        'body' => $body,
                        'call_data' => $callData,
                        'is_encrypted' => false,
                    ]);

                    $message->load(['sender', 'attachments', 'reactions.user']);
                    RealtimeDispatcher::messageSent($message);
                }
            } else {
                Log::warning('Call session missing caller_id or callee_id during finalize', [
                    'session_id' => $session->id,
                    'caller_id' => $session->caller_id,
                    'callee_id' => $session->callee_id,
                    'reason' => $endReason,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to create call message during finalize: '.$e->getMessage(), [
                'session_id' => $session->id,
            ]);
        }

        $action = $isDeclined ? 'declined' : 'ended';
        $payload = json_encode([
            'session_id' => $session->id,
            'action' => $action,
            'reason' => $endReason,
        ]);
        broadcast(new CallSignal($session, $payload))->toOthers();

        $this->notifyCallEndedWebPush($session, $endingUser, $endReason);

        return true;
    }

    protected function notifyCallEndedWebPush(
        CallSession $session,
        ?User $endingUser,
        string $endReason
    ): void {
        $webPush = app(WebPushService::class);
        $notifyIds = $this->participantUserIds($session);

        if ($endingUser !== null) {
            $endingId = (int) $endingUser->id;
            $peerId = $endingId === (int) $session->caller_id
                ? (int) ($session->callee_id ?? 0)
                : (int) ($session->caller_id ?? 0);
            $notifyIds = $peerId > 0 ? [$peerId] : array_values(array_filter(
                $notifyIds,
                fn (int $id) => $id !== $endingId
            ));
        }

        foreach (array_unique($notifyIds) as $userId) {
            try {
                $webPush->sendCallEnded($userId, (int) $session->id, $endReason);
            } catch (\Throwable $e) {
                Log::warning('Web push call-ended failed', [
                    'session_id' => $session->id,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
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
            ->whereIn('status', ['invited', 'joined'])
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique([...$ids, ...$participantIds]));
    }

    public function callEndMessageBody(
        string $type,
        $duration,
        bool $isMissed,
        bool $isDeclined,
        bool $titleCase
    ): string {
        $callIcon = $type === 'video' ? '📹' : '📞';
        $callTypeText = $type === 'video'
            ? ($titleCase ? 'Video call' : 'video call')
            : ($titleCase ? 'Voice call' : 'voice call');

        if ($isDeclined) {
            return "{$callIcon} Declined {$callTypeText}";
        }
        if ($isMissed) {
            return "{$callIcon} Missed {$callTypeText}";
        }
        if ($duration && $duration > 0) {
            $durationSeconds = (int) round((float) $duration);
            $durationText = $durationSeconds < 60
                ? "{$durationSeconds}s"
                : gmdate('i:s', $durationSeconds);

            return "{$callIcon} {$callTypeText} ({$durationText})";
        }

        return "{$callIcon} {$callTypeText}";
    }

    protected function patchActiveCallInviteMessages(
        CallSession $session,
        $duration,
        bool $isMissed,
        bool $isDeclined = false
    ): bool {
        $sessionId = (int) $session->id;
        $any = false;

        if ($session->group_id) {
            $candidates = GroupMessage::query()
                ->where('group_id', $session->group_id)
                ->whereNotNull('call_data')
                ->orderByDesc('id')
                ->limit(120)
                ->get();

            foreach ($candidates as $gm) {
                if (! $this->callInviteRowMatchesSession($gm->call_data, $sessionId)) {
                    continue;
                }
                $this->finalizeGroupCallInviteRow($gm, $session, $duration, $isMissed, $isDeclined);
                $any = true;
            }
        } elseif ($session->caller_id && $session->callee_id) {
            $conversation = Conversation::findOrCreateDirect($session->caller_id, $session->callee_id);
            $candidates = Message::query()
                ->where('conversation_id', $conversation->id)
                ->whereNotNull('call_data')
                ->orderByDesc('id')
                ->limit(120)
                ->get();

            foreach ($candidates as $message) {
                if (! $this->callInviteRowMatchesSession($message->call_data, $sessionId)) {
                    continue;
                }
                $this->finalizeDirectCallInviteRow($message, $session, $duration, $isMissed, $isDeclined);
                $any = true;
            }
        }

        return $any;
    }

    private function callInviteRowMatchesSession(?array $callData, int $sessionId): bool
    {
        if (! $callData || ! isset($callData['session_id'])) {
            return false;
        }
        if ((int) $callData['session_id'] !== $sessionId) {
            return false;
        }
        $st = $callData['status'] ?? '';

        return $st === 'calling' || $st === 'ongoing';
    }

    private function finalizeGroupCallInviteRow(
        GroupMessage $gm,
        CallSession $session,
        $duration,
        bool $isMissed,
        bool $isDeclined = false
    ): void {
        $body = $this->callEndMessageBody($session->type, $duration, $isMissed, $isDeclined, false);

        $cd = $gm->call_data ?? [];
        $cd = array_merge($cd, [
            'type' => $session->type,
            'caller_id' => $session->caller_id,
            'session_id' => $session->id,
            'status' => 'ended',
            'duration' => $duration,
            'missed' => $isMissed,
            'is_missed' => $isMissed,
            'declined' => $isDeclined,
        ]);
        unset($cd['call_link']);

        $gm->body = $body;
        $gm->call_data = $cd;
        $gm->save();

        $gm->load(['sender', 'attachments', 'reactions.user']);
        RealtimeDispatcher::groupMessageSent($gm);
    }

    private function finalizeDirectCallInviteRow(
        Message $message,
        CallSession $session,
        $duration,
        bool $isMissed,
        bool $isDeclined = false
    ): void {
        $body = $this->callEndMessageBody($session->type, $duration, $isMissed, $isDeclined, true);

        $cd = $message->call_data ?? [];
        $cd = array_merge($cd, [
            'type' => $session->type,
            'caller_id' => $session->caller_id,
            'callee_id' => $session->callee_id,
            'session_id' => $session->id,
            'status' => 'ended',
            'duration' => $duration,
            'ended_at' => now()->toISOString(),
            'missed' => $isMissed,
            'is_missed' => $isMissed,
            'declined' => $isDeclined,
        ]);
        unset($cd['call_link']);

        $message->body = $body;
        $message->call_data = $cd;
        $message->save();

        $message->load(['sender', 'attachments', 'reactions.user']);
        RealtimeDispatcher::messageSent($message);
    }
}
