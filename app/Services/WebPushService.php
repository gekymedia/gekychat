<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\WebPushMessageNotification;
use Illuminate\Support\Facades\Log;

class WebPushService
{
    public function userHasSubscriptions(int $userId): bool
    {
        $user = User::find($userId);

        return $user && $user->pushSubscriptions()->exists();
    }

    public function sendToUser(int $userId, string $title, string $body, array $data = [], ?string $tag = null): void
    {
        $user = User::find($userId);
        if (! $user || ! $user->pushSubscriptions()->exists()) {
            return;
        }

        try {
            $user->notify(new WebPushMessageNotification($title, $body, $data, $tag));
        } catch (\Throwable $e) {
            Log::warning('Web push delivery failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendMessageNotification(
        int $recipientId,
        string $senderName,
        string $messageBody,
        int $conversationId,
        int $messageId,
        bool $isGroup = false,
        ?int $groupId = null,
    ): void {
        $url = $isGroup && $groupId
            ? '/g/' . $groupId
            : '/c/' . $conversationId;

        $this->sendToUser(
            $recipientId,
            $senderName,
            $messageBody,
            [
                'type' => $isGroup ? 'group_message' : 'message',
                'conversation_id' => (string) $conversationId,
                'group_id' => $groupId ? (string) $groupId : '',
                'message_id' => (string) $messageId,
                'url' => $url,
            ],
            $isGroup ? 'gekychat_group_' . $groupId : 'gekychat_conv_' . $conversationId,
        );
    }

    public function sendCallInvite(
        int $recipientId,
        string $callerName,
        int $callId,
        string $callType,
        ?int $conversationId = null,
        ?int $groupId = null,
        string $callerPhone = '',
        ?int $callerId = null,
    ): void {
        $callTypeText = $callType === 'video' ? 'video call' : 'voice call';
        $url = $groupId ? '/g/' . $groupId : ($conversationId ? '/c/' . $conversationId : '/');

        $this->sendToUser(
            $recipientId,
            'Incoming ' . $callTypeText,
            $callerName . ' is calling you',
            [
                'type' => 'call_invite',
                'call_id' => (string) $callId,
                'session_id' => (string) $callId,
                'call_type' => $callType,
                'caller_id' => (string) ($callerId ?? ''),
                'caller_name' => $callerName,
                'caller_phone' => $callerPhone,
                'conversation_id' => $conversationId ? (string) $conversationId : '',
                'group_id' => $groupId ? (string) $groupId : '',
                'url' => $url,
            ],
            'gekychat_call_' . $callId,
        );
    }
}
