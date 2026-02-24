<?php

namespace App\Listeners;

use App\Events\GroupMessageSent;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendGroupMessageNotification implements ShouldQueue
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
     * When a group message is sent, send FCM notification to all members
     * This triggers background sync on their devices (even when app is closed)
     */
    public function handle(GroupMessageSent $event): void
    {
        $message = $event->message;
        $group = $message->group;
        
        // Get group members (exclude sender)
        $recipientIds = $group->members()
            ->where('users.id', '!=', $message->sender_id)
            ->pluck('users.id')
            ->toArray();
        
        if (empty($recipientIds)) {
            Log::info('No recipients for group message notification', [
                'message_id' => $message->id,
                'group_id' => $group->id,
            ]);
            return;
        }
        
        // Get sender name
        $senderName = $message->sender->name ?? $message->sender->phone ?? 'Someone';
        
        // Missed-call group message: send as missed_call type so mobile shows in Calls group
        $callData = $message->call_data ?? null;
        $isMissedCall = $callData && !empty($callData['is_missed']);
        
        if ($isMissedCall) {
            foreach ($recipientIds as $recipientId) {
                try {
                    $senderAvatarUrl = $message->sender->avatar_url ?? null;
                    $this->fcmService->sendMissedCallNotification(
                        $recipientId,
                        $senderName,
                        0,
                        $message->id,
                        $senderAvatarUrl,
                        $group->id,
                        $group->name
                    );
                    Log::info('FCM missed-call (group) notification sent', [
                        'message_id' => $message->id,
                        'group_id' => $group->id,
                        'recipient_id' => $recipientId,
                        'sender_name' => $senderName,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send FCM missed-call (group) notification', [
                        'message_id' => $message->id,
                        'group_id' => $group->id,
                        'recipient_id' => $recipientId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            return;
        }
        
        // Get message body (truncate if too long)
        $messageBody = $message->body ?? '[Media]';
        if ($message->attachments->isNotEmpty()) {
            $messageBody = '📎 ' . ($message->body ?: 'Sent an attachment');
        }
        
        // Format notification title with group name (WhatsApp-style: "Group – Sender")
        $notificationTitle = "{$group->name} – {$senderName}";

        // Attachment type for mobile to show "📷 Photo" etc. when body is empty
        $attachmentType = null;
        if ($message->attachments->isNotEmpty()) {
            $mime = $message->attachments->first()->mime_type ?? '';
            if (str_starts_with($mime, 'image/')) {
                $attachmentType = 'image';
            } elseif (str_starts_with($mime, 'video/')) {
                $attachmentType = 'video';
            } elseif (str_starts_with($mime, 'audio/')) {
                $attachmentType = 'audio';
            } else {
                $attachmentType = 'document';
            }
        }

        // Send FCM notification to each member
        // This will trigger background sync on their device
        foreach ($recipientIds as $recipientId) {
            try {
                $data = [
                    'type' => 'new_message',
                    'payload_type' => 'group', // FCM v1 rejects key "message_type"
                    'group_id' => (string) $group->id,
                    'message_id' => (string) $message->id,
                    'sender_name' => $senderName,
                    'group_name' => $group->name,
                    'body' => $messageBody,
                    'title' => $notificationTitle,
                    'message' => $messageBody,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ];
                if ($attachmentType !== null) {
                    $data['attachment_type'] = $attachmentType;
                    $data['mime_type'] = $message->attachments->first()->mime_type ?? '';
                }
                $senderAvatarUrl = $message->sender->avatar_url ?? null;
                if ($senderAvatarUrl !== null && $senderAvatarUrl !== '') {
                    $data['sender_avatar'] = $senderAvatarUrl;
                }
                $collapseKey = 'gekychat_group_' . $group->id;
                $this->fcmService->sendDataOnlyToUser($recipientId, $data, $collapseKey);
                
                Log::info('FCM group message notification sent', [
                    'message_id' => $message->id,
                    'group_id' => $group->id,
                    'recipient_id' => $recipientId,
                    'sender_name' => $senderName,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send FCM group message notification', [
                    'message_id' => $message->id,
                    'group_id' => $group->id,
                    'recipient_id' => $recipientId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
