<?php

namespace App\Services;

use App\Events\GroupMessageSent;
use App\Events\MessageSent;
use App\Models\GroupMessage;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches message events so WebSocket broadcast AND EventServiceProvider
 * listeners (FCM, inbox fanout, auto-reply) all run.
 *
 * broadcast() alone only hits WebSocket — registered listeners never fire.
 */
class RealtimeDispatcher
{
    public static function messageSent(Message $message): void
    {
        try {
            event(new MessageSent($message));
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch MessageSent', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function groupMessageSent(GroupMessage $message): void
    {
        try {
            event(new GroupMessageSent($message));
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch GroupMessageSent', [
                'message_id' => $message->id,
                'group_id' => $message->group_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
