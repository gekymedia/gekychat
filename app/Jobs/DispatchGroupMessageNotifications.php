<?php

namespace App\Jobs;

use App\Events\GroupMessageSent;
use App\Listeners\SendGroupMessageNotification;
use App\Listeners\SendMentionNotification;
use App\Models\GroupMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends group FCM/WebPush after the HTTP response (via afterResponse).
 */
class DispatchGroupMessageNotifications
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $messageId) {}

    public function handle(
        SendGroupMessageNotification $sendGroupMessage,
        SendMentionNotification $sendMention,
    ): void {
        $message = GroupMessage::with([
            'sender',
            'attachments',
            'group.members',
            'replyTo',
            'forwardedFrom',
            'reactions.user',
        ])->find($this->messageId);

        if (! $message) {
            return;
        }

        $event = new GroupMessageSent($message);

        try {
            $sendGroupMessage->handle($event);
        } catch (\Throwable $e) {
            Log::warning('DispatchGroupMessageNotifications: FCM failed', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $sendMention->handle($event);
        } catch (\Throwable $e) {
            Log::warning('DispatchGroupMessageNotifications: mention notify failed', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
