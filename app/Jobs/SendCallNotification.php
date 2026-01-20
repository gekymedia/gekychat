<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\CallSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SendCallNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public User $callee;
    public CallSession $call;
    public User $caller;

    public function __construct(User $callee, CallSession $call, User $caller)
    {
        $this->callee = $callee;
        $this->call = $call;
        $this->caller = $caller;
    }

    public function handle()
    {
        // Get all device tokens for the callee using DeviceToken model
        $tokens = \App\Models\DeviceToken::where('user_id', $this->callee->id)
            ->pluck('token')
            ->filter()
            ->toArray();
        
        if (empty($tokens)) {
            Log::info("No FCM tokens found for user {$this->callee->id}");
            return;
        }

        $callTypeText = $this->call->type === 'video' ? 'video call' : 'voice call';
        $callerName = $this->caller->name ?? $this->caller->phone ?? 'Someone';

        // Prepare notification payload
        $notification = [
            'title' => 'Incoming ' . $callTypeText,
            'body' => $callerName . ' is calling you',
            'sound' => 'default',
        ];

        $data = [
            'type' => 'call_invite',
            'call_id' => $this->call->id,
            'session_id' => $this->call->id,
            'caller_id' => $this->caller->id,
            'caller_name' => $callerName,
            'caller_avatar' => $this->caller->avatar_url,
            'call_type' => $this->call->type,
            'conversation_id' => $this->call->conversation_id,
            'group_id' => $this->call->group_id,
            'action' => 'incoming_call',
            'priority' => 'high',
        ];

        // Send to all devices
        foreach ($tokens as $token) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'key=' . config('services.fcm.server_key'),
                    'Content-Type' => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $token,
                    'notification' => $notification,
                    'data' => $data,
                    'priority' => 'high',
                    'sound' => 'default',
                ]);

                if (!$response->successful()) {
                    Log::warning("Failed to send call notification to token: " . substr($token, 0, 20) . '...');
                }
            } catch (\Exception $e) {
                Log::error("Error sending call notification: " . $e->getMessage());
            }
        }
    }
}
