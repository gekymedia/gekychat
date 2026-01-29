<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Events\GroupMessageSent;
use App\Services\FcmService;
use Illuminate\Support\Facades\Log;

class SendMentionNotification
{
    protected $fcmService;
    
    public function __construct(FcmService $fcmService)
    {
        $this->fcmService = $fcmService;
    }
    
    /**
     * Handle the event (works for both MessageSent and GroupMessageSent)
     */
    public function handle($event)
    {
        $message = $event->message;
        
        // Get mentions for this message
        $mentions = $message->mentions()->with('mentionedUser')->get();
        
        if ($mentions->isEmpty()) {
            return;
        }
        
        Log::info("Processing {$mentions->count()} mentions for message #{$message->id}");
        
        foreach ($mentions as $mention) {
            $user = $mention->mentionedUser;
            
            if (!$user) {
                continue;
            }
            
            // Check notification preferences
            if (!$user->notificationPreferences?->push_mentions ?? true) {
                Log::info("Skipping mention notification - user #{$user->id} has mentions disabled");
                continue;
            }
            
            // Check quiet hours
            if ($user->notificationPreferences?->isQuietHours()) {
                Log::info("Skipping mention notification - quiet hours for user #{$user->id}");
                continue;
            }
            
            // Prepare notification data
            $title = $mention->mentionedByUser->name . ' mentioned you';
            $body = strlen($message->body) > 100 
                ? substr($message->body, 0, 100) . '...' 
                : $message->body;
            
            $data = [
                'type' => 'mention',
                'mention_id' => $mention->id,
                'message_id' => $message->id,
            ];
            
            // Add conversation or group ID
            if (isset($message->conversation_id)) {
                $data['conversation_id'] = $message->conversation_id;
            } elseif (isset($message->group_id)) {
                $data['group_id'] = $message->group_id;
            }
            
            // Send FCM notification
            try {
                $this->fcmService->sendToUser($user->id, [
                    'title' => $title,
                    'body' => $body,
                    'data' => $data,
                ]);
                
                // Mark notification as sent
                $mention->markNotificationSent();
                
                Log::info("Sent mention notification to user #{$user->id} for mention #{$mention->id}");
                
            } catch (\Exception $e) {
                Log::error('Failed to send mention notification', [
                    'mention_id' => $mention->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
