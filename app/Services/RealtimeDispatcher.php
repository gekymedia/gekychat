<?php

namespace App\Services;

use App\Events\GroupMessageSent;
use App\Events\MessageSent;
use App\Jobs\DispatchGroupMessageNotifications;
use App\Jobs\DispatchMessageNotifications;
use App\Jobs\DispatchMessageSideEffects;
use App\Listeners\BroadcastUserInboxGroupMessage;
use App\Listeners\BroadcastUserInboxMessage;
use App\Models\GroupMessage;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches WebSocket broadcasts immediately, then FCM/push after the HTTP response.
 *
 * Order: inbox fanout → conversation broadcast → (after response) FCM + auto-reply.
 * A slow FCM round-trip must never delay WebSocket delivery.
 */
class RealtimeDispatcher
{
    public static function messageSent(Message $message): void
    {
        $event = new MessageSent($message);

        static::runListeners($event, [
            BroadcastUserInboxMessage::class,
        ], 'MessageSent:inbox', $message->id);

        static::safeBroadcast($event, 'MessageSent', $message->id);

        DispatchMessageNotifications::dispatch($message->id)->afterResponse();
        DispatchMessageSideEffects::dispatch($message->id)->afterResponse();
    }

    public static function groupMessageSent(GroupMessage $message): void
    {
        $event = new GroupMessageSent($message);

        static::runListeners($event, [
            BroadcastUserInboxGroupMessage::class,
        ], 'GroupMessageSent:inbox', $message->id);

        static::safeBroadcast($event, 'GroupMessageSent', $message->id);

        DispatchGroupMessageNotifications::dispatch($message->id)->afterResponse();
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
            Log::warning("{$label} broadcast failed", [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
