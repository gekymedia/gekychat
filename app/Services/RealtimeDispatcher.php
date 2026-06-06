<?php

namespace App\Services;

use App\Events\GroupMessageSent;
use App\Events\MessageSent;
use App\Listeners\BroadcastUserInboxGroupMessage;
use App\Listeners\BroadcastUserInboxMessage;
use App\Listeners\ProcessAutoReply;
use App\Listeners\SendGroupMessageNotification;
use App\Listeners\SendMentionNotification;
use App\Listeners\SendMessageNotification;
use App\Models\GroupMessage;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches message notifications and WebSocket broadcasts.
 *
 * Listeners (FCM, inbox fanout) run FIRST and are isolated from broadcast failures.
 * WebSocket broadcast runs second — a Reverb/nginx misconfig must not block FCM.
 */
class RealtimeDispatcher
{
    public static function messageSent(Message $message): void
    {
        $event = new MessageSent($message);

        static::runListeners($event, [
            BroadcastUserInboxMessage::class,
            ProcessAutoReply::class,
            SendMessageNotification::class,
            SendMentionNotification::class,
        ], 'MessageSent', $message->id);

        static::safeBroadcast($event, 'MessageSent', $message->id);
    }

    public static function groupMessageSent(GroupMessage $message): void
    {
        $event = new GroupMessageSent($message);

        static::runListeners($event, [
            BroadcastUserInboxGroupMessage::class,
            SendGroupMessageNotification::class,
            SendMentionNotification::class,
        ], 'GroupMessageSent', $message->id);

        static::safeBroadcast($event, 'GroupMessageSent', $message->id);
    }

    /**
     * @param  array<int, class-string>  $listeners
     */
    private static function runListeners(object $event, array $listeners, string $label, int $messageId): void
    {
        foreach ($listeners as $listenerClass) {
            try {
                app($listenerClass)->handle($event);
            } catch (\Throwable $e) {
                Log::warning("{$label} listener failed", [
                    'listener' => $listenerClass,
                    'message_id' => $messageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private static function safeBroadcast(object $event, string $label, int $messageId): void
    {
        try {
            broadcast($event);
        } catch (\Throwable $e) {
            Log::warning("{$label} broadcast failed (listeners already ran)", [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
