<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Events\UserInboxMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BroadcastUserInboxMessage
{
    public function handle(MessageSent $event): void
    {
        $startedAt = microtime(true);
        $message = $event->message;
        $message->loadMissing('conversation.members');
        $conversation = $message->conversation;
        if (! $conversation || ! $conversation->relationLoaded('members')) {
            return;
        }
        $recipientCount = 0;
        foreach ($conversation->members as $member) {
            if ((int) $member->id === (int) $message->sender_id) {
                continue;
            }
            broadcast(new UserInboxMessage($message, (int) $member->id));
            $recipientCount++;
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        Cache::increment('rt:inbox:dm:events_total');
        Cache::increment('rt:inbox:dm:recipients_total', $recipientCount);
        Cache::put('rt:inbox:dm:last_duration_ms', $durationMs, now()->addHours(6));
        if ($durationMs > 250) {
            Log::warning('realtime.dm_inbox_fanout_slow', [
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'recipients' => $recipientCount,
                'duration_ms' => $durationMs,
            ]);
        }
    }
}
