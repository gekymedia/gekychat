<?php

namespace App\Jobs;

use App\Events\MessageSent;
use App\Listeners\SendMentionNotification;
use App\Listeners\SendMessageNotification;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends FCM/WebPush after the HTTP response (via afterResponse).
 * Not queued — runs in-process after the client receives 201 + WebSocket events.
 */
class DispatchMessageNotifications
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $messageId) {}

    public function handle(
        SendMessageNotification $sendMessage,
        SendMentionNotification $sendMention,
    ): void {
        $message = Message::with([
            'sender',
            'attachments',
            'conversation.members',
            'replyTo',
            'forwardedFrom',
            'reactions.user',
            'referencedStatus',
        ])->find($this->messageId);

        if (! $message) {
            return;
        }

        $event = new MessageSent($message);

        try {
            $sendMessage->handle($event);
        } catch (\Throwable $e) {
            Log::warning('DispatchMessageNotifications: FCM failed', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $sendMention->handle($event);
        } catch (\Throwable $e) {
            Log::warning('DispatchMessageNotifications: mention notify failed', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
