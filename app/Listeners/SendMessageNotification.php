<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendMessageNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected $fcmService;

    /**
     * Create the event listener.
     */
    public function __construct(FcmService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Handle the event.
     * 
     * When a message is sent, send FCM notification to recipient
     * This triggers background sync on the recipient's device (even when app is closed)
     */
    public function handle(MessageSent $event): void
    {
        $message = $event->message;
        
        // Get conversation participants (exclude sender)
        $conversation = $message->conversation;
        $recipientIds = $conversation->members()
            ->where('users.id', '!=', $message->sender_id)
            ->pluck('users.id')
            ->toArray();
        
        if (empty($recipientIds)) {
            Log::info('No recipients for message notification', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
            ]);
            return;
        }
        
        // Get sender name
        $senderName = $message->sender->name ?? $message->sender->phone ?? 'Someone';
        
        // Get message body (truncate if too long)
        $messageBody = $message->body ?? '';
        if ($message->attachments->isNotEmpty() && $messageBody === '') {
            $messageBody = '📎 ' . ($message->body ?: 'Sent an attachment');
        }
        
        // Send FCM notification to each recipient
        // This will trigger background sync on their device
        foreach ($recipientIds as $recipientId) {
            try {
                $this->fcmService->sendMessageNotification(
                    $recipientId,
                    $senderName,
                    $messageBody,
                    $conversation->id,
                    $message->id,
                    $message->attachments->isNotEmpty() ? $message->attachments->first()->mime_type : null
                );
                
                Log::info('FCM message notification sent', [
                    'message_id' => $message->id,
                    'recipient_id' => $recipientId,
                    'sender_name' => $senderName,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send FCM message notification', [
                    'message_id' => $message->id,
                    'recipient_id' => $recipientId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
