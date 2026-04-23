<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Services\FcmService;
use Illuminate\Support\Facades\Log;

/**
 * Sends FCM data messages when a DM is sent. Runs synchronously so delivery is not
 * delayed by the queue worker (same request as broadcast + HTTP response).
 */
class SendMessageNotification
{
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
        
        // Missed-call message: send as missed_call type so mobile shows in Calls group
        $callData = $message->call_data ?? null;
        $isMissedCall = $callData && (
            !empty($callData['missed']) ||
            !empty($callData['is_missed'])
        );
        
        if ($isMissedCall) {
            foreach ($recipientIds as $recipientId) {
                try {
                    $senderAvatarUrl = $message->sender->avatar_url ?? null;
                    $this->fcmService->sendMissedCallNotification(
                        $recipientId,
                        $senderName,
                        $conversation->id,
                        $message->id,
                        $senderAvatarUrl,
                        null,
                        null
                    );
                    Log::info('FCM missed-call notification sent', [
                        'message_id' => $message->id,
                        'recipient_id' => $recipientId,
                        'sender_name' => $senderName,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send FCM missed-call notification', [
                        'message_id' => $message->id,
                        'recipient_id' => $recipientId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            return;
        }
        
        // Get message body for push preview (never leak ciphertext)
        $messageBody = $message->body ?? '';
        if ($message->is_encrypted) {
            $messageBody = $message->attachments->isNotEmpty() ? '📎 Attachment' : '[Encrypted Message]';
        } elseif ($message->attachments->isNotEmpty() && $messageBody === '') {
            $messageBody = '📎 '.($message->body ?: 'Sent an attachment');
        }

        $attachmentPreviewUrl = null;
        $firstAttachmentId = null;
        if ($message->attachments->isNotEmpty()) {
            $fa = $message->attachments->first();
            $firstAttachmentId = (int) $fa->id;
            $mime = (string) ($fa->mime_type ?? '');
            if (str_starts_with($mime, 'image/')) {
                $attachmentPreviewUrl = $fa->thumbnail_url ?: ($fa->compressed_url ?: $fa->url);
            } elseif (str_starts_with($mime, 'video/')) {
                $attachmentPreviewUrl = $fa->thumbnail_url;
            }
        }
        
        // Send FCM notification to each recipient
        // This will trigger background sync on their device
        foreach ($recipientIds as $recipientId) {
            try {
                $senderAvatarUrl = $message->sender->avatar_url ?? null;
                $this->fcmService->sendMessageNotification(
                    $recipientId,
                    $senderName,
                    $messageBody,
                    $conversation->id,
                    $message->id,
                    $message->attachments->isNotEmpty() ? $message->attachments->first()->mime_type : null,
                    $senderAvatarUrl,
                    $message->referenced_status_id ? (int) $message->referenced_status_id : null,
                    (int) $message->sender_id,
                    $attachmentPreviewUrl,
                    $firstAttachmentId
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
