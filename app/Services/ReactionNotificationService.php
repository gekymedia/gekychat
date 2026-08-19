<?php

namespace App\Services;

use App\Events\MessageReacted;
use App\Models\GroupMessage;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ReactionNotificationService
{
    public function __construct(
        private FcmService $fcm
    ) {
    }

    public function onDirectMessageReaction(Message $message, User $reactor, string $emoji): void
    {
        $ownerId = (int) $message->sender_id;
        $reactorId = (int) $reactor->id;

        if (!$message->conversation_id || $ownerId === $reactorId) {
            return;
        }

        broadcast(new MessageReacted(
            (int) $message->id,
            $reactorId,
            $emoji,
            (int) $message->conversation_id,
            null
        ))->toOthers();

        if (!$this->shouldSendPush($ownerId)) {
            return;
        }

        $actorName = $reactor->name ?? $reactor->username ?? 'Someone';
        try {
            $this->fcm->sendReactionNotification(
                $ownerId,
                $actorName,
                $emoji,
                (int) $message->id,
                (int) $message->conversation_id,
                null
            );
        } catch (\Throwable $e) {
            Log::warning('ReactionNotificationService: DM push failed', [
                'message_id' => $message->id,
                'recipient_id' => $ownerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function onGroupMessageReaction(GroupMessage $message, User $reactor, string $emoji): void
    {
        $ownerId = (int) $message->sender_id;
        $reactorId = (int) $reactor->id;

        if (!$message->group_id || $ownerId === $reactorId) {
            return;
        }

        broadcast(new MessageReacted(
            (int) $message->id,
            $reactorId,
            $emoji,
            null,
            (int) $message->group_id
        ))->toOthers();

        if (!$this->shouldSendPush($ownerId)) {
            return;
        }

        $actorName = $reactor->name ?? $reactor->username ?? 'Someone';
        try {
            $this->fcm->sendReactionNotification(
                $ownerId,
                $actorName,
                $emoji,
                (int) $message->id,
                null,
                (int) $message->group_id
            );
        } catch (\Throwable $e) {
            Log::warning('ReactionNotificationService: group push failed', [
                'message_id' => $message->id,
                'recipient_id' => $ownerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function shouldSendPush(int $recipientId): bool
    {
        $user = User::with('notificationPreferences')->find($recipientId);
        if (!$user) {
            return false;
        }

        $prefs = $user->notificationPreferences;
        if ($prefs?->isQuietHours()) {
            return false;
        }

        return $prefs?->push_reactions ?? true;
    }
}
