<?php

namespace App\Jobs;

use App\Events\MessageSent;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue message broadcast for reliable delivery (Phase 2 - event queue).
 * Dispatched after a message is stored so Pusher broadcast is retried on failure.
 */
class BroadcastMessageSentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $messageId;

    public function __construct(int $messageId)
    {
        $this->messageId = $messageId;
    }

    public function handle(): void
    {
        $message = Message::find($this->messageId);
        if (!$message) {
            Log::warning("BroadcastMessageSentJob: message {$this->messageId} not found");
            return;
        }
        broadcast(new MessageSent($message))->toOthers();
    }
}
