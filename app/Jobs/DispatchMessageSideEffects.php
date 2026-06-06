<?php

namespace App\Jobs;

use App\Events\MessageSent;
use App\Listeners\ProcessAutoReply;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs auto-reply after WebSocket delivery (via afterResponse).
 */
class DispatchMessageSideEffects
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $messageId) {}

    public function handle(ProcessAutoReply $processAutoReply): void
    {
        $message = Message::with(['sender', 'conversation.members'])->find($this->messageId);

        if (! $message) {
            return;
        }

        try {
            $processAutoReply->handle(new MessageSent($message));
        } catch (\Throwable $e) {
            Log::warning('DispatchMessageSideEffects: auto-reply failed', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
