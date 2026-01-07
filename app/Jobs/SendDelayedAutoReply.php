<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\Conversation;
use App\Events\MessageSent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * AUTO-REPLY: Send delayed auto-reply message
 * 
 * This job is dispatched when an auto-reply has a delay_seconds > 0
 */
class SendDelayedAutoReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $message;

    /**
     * Create a new job instance.
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Reload message to ensure it's fresh
        $message = Message::find($this->message->id);
        if (!$message) {
            return;
        }

        $conversation = $message->conversation;
        
        // Mark as delivered for recipients
        $recipients = $conversation->members()->where('users.id', '!=', $message->sender_id)->get();
        foreach ($recipients as $recipient) {
            $message->markAsDeliveredFor($recipient->id);
        }

        // Load relationships
        $message->load(['sender', 'attachments', 'replyTo', 'forwardedFrom', 'reactions.user']);

        // Broadcast message
        broadcast(new MessageSent($message))->toOthers();
    }
}
