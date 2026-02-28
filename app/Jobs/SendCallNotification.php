<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\CallSession;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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

    public function handle(FcmService $fcm)
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

        // IMPORTANT: Use data-only FCM (no notification block) for incoming calls.
        // When FCM has a notification block, Android shows it immediately and may NOT
        // call the background handler, so CallKit/flutter_callkit_incoming never gets
        // triggered to show the full-screen ringing UI.
        //
        // With data-only FCM:
        // 1. Android calls the background handler
        // 2. Background handler calls CallKitService.showIncomingCallFromFcmData()
        // 3. CallKit shows full-screen incoming call UI with ringing
        $data = [
            'type' => 'call_invite',
            'call_id' => (string) $this->call->id,
            'session_id' => (string) $this->call->id,
            'caller_id' => (string) $this->caller->id,
            'caller_name' => $callerName,
            'caller_avatar' => (string) ($this->caller->avatar_url ?? ''),
            'call_type' => $this->call->type,
            'conversation_id' => (string) ($this->call->conversation_id ?? ''),
            'group_id' => (string) ($this->call->group_id ?? ''),
            'action' => 'incoming_call',
            'priority' => 'high',
            // Include title/body in data for the app to use if needed
            'title' => 'Incoming ' . $callTypeText,
            'body' => $callerName . ' is calling you',
        ];

        foreach ($tokens as $token) {
            try {
                // Use sendDataOnlyToToken instead of sendToToken to avoid notification block
                if (!$fcm->sendDataOnlyToToken($token, $data)) {
                    Log::warning('Failed to send call notification to token: ' . substr($token, 0, 20) . '...');
                }
            } catch (\Exception $e) {
                Log::error('Error sending call notification: ' . $e->getMessage());
            }
        }
    }
}
