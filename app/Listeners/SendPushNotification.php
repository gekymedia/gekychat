<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Services\FcmService;
use Illuminate\Support\Facades\Log;

class SendPushNotification
{
    protected $fcmService;

    public function __construct(FcmService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    public function handle(MessageSent $event): void
    {
        try {
            $message = $event->message;
            $conversation = Conversation::with('members')->find($message->conversation_id);
            
            if (!$conversation || !$message->sender) {
                return;
            }

            // Get sender info
            $senderName = $message->sender->name ?? $message->sender->phone ?? 'Someone';
            $messageBody = $message->body ?? '';
            
            // Truncate encrypted messages
            if ($message->is_encrypted) {
                $messageBody = '[Encrypted Message]';
            }

            // Send notification to all conversation members except the sender
            foreach ($conversation->members as $member) {
                if ($member->id === $message->sender_id) {
                    continue; // Skip sender
                }

                // Check if user is online (within last 5 minutes)
                $isOnline = $member->last_seen_at && 
                           $member->last_seen_at->gt(now()->subMinutes(5));
                
                // Still send notification even if online (user might be on different device)
                // The frontend can filter notifications if needed
                
                Log::info('Sending push notification', [
                    'recipient_id' => $member->id,
                    'sender_name' => $senderName,
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                ]);

                $this->fcmService->sendMessageNotification(
                    $member->id,
                    $senderName,
                    $messageBody,
                    $conversation->id,
                    $message->id
                );
            }
        } catch (\Exception $e) {
            Log::error('Error sending push notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

