<?php

namespace App\Services;

use App\Models\CallSession;
use App\Models\GroupMessage;
use App\Models\Message;
use App\Models\Status;

/**
 * Server-side product action tracking (reliable even when clients omit events).
 */
class ProductAnalyticsTracker
{
    public static function messageSent(Message $message): void
    {
        if (!empty($message->call_data)) {
            return;
        }

        static::action(
            (int) $message->sender_id,
            'message_sent',
            'chats',
            [
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'has_attachments' => $message->relationLoaded('attachments')
                    ? $message->attachments->isNotEmpty()
                    : null,
            ]
        );
    }

    public static function groupMessageSent(GroupMessage $message): void
    {
        if (!empty($message->call_data)) {
            return;
        }

        static::action(
            (int) $message->sender_id,
            'message_sent',
            'groups',
            [
                'group_id' => $message->group_id,
                'message_id' => $message->id,
            ]
        );
    }

    public static function statusPosted(Status $status): void
    {
        static::action(
            (int) $status->user_id,
            'status_posted',
            'status',
            [
                'status_id' => $status->id,
                'type' => $status->type,
            ]
        );
    }

    public static function callStarted(CallSession $call, int $userId): void
    {
        static::action(
            $userId,
            'call_started',
            'calls',
            [
                'session_id' => $call->id,
                'type' => $call->type,
                'is_group' => (bool) $call->group_id,
                'is_meeting' => (bool) $call->is_meeting,
            ]
        );
    }

    public static function giftSent(int $fromUserId, int $coins, ?int $toUserId = null, ?int $postId = null): void
    {
        static::action(
            $fromUserId,
            'gift_sent',
            'wallet',
            array_filter([
                'coins' => $coins,
                'to_user_id' => $toUserId,
                'post_id' => $postId,
            ], fn ($v) => $v !== null)
        );
    }

    public static function action(
        int $userId,
        string $actionKey,
        ?string $featureKey = null,
        array $properties = [],
        string $platform = 'api',
    ): void {
        try {
            app(ProductAnalyticsIngestService::class)->trackServerAction(
                $userId,
                $actionKey,
                $featureKey,
                $properties,
                $platform
            );
        } catch (\Throwable) {
            // Analytics must never break core flows.
        }
    }
}
