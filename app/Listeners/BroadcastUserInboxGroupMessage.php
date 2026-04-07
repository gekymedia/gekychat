<?php

namespace App\Listeners;

use App\Events\GroupMessageSent;
use App\Events\UserInboxGroupMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BroadcastUserInboxGroupMessage
{
    public function handle(GroupMessageSent $event): void
    {
        $startedAt = microtime(true);
        $message = $event->message;
        $message->loadMissing('group.members');
        $group = $message->group;
        if (! $group || ! $group->relationLoaded('members')) {
            return;
        }
        $recipientCount = 0;
        foreach ($group->members as $member) {
            if ((int) $member->id === (int) $message->sender_id) {
                continue;
            }
            broadcast(new UserInboxGroupMessage($message, (int) $member->id));
            $recipientCount++;
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        Cache::increment('rt:inbox:group:events_total');
        Cache::increment('rt:inbox:group:recipients_total', $recipientCount);
        Cache::put('rt:inbox:group:last_duration_ms', $durationMs, now()->addHours(6));
        if ($durationMs > 350) {
            Log::warning('realtime.group_inbox_fanout_slow', [
                'message_id' => $message->id,
                'group_id' => $message->group_id,
                'recipients' => $recipientCount,
                'duration_ms' => $durationMs,
            ]);
        }
    }
}
