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

        $notification = [
            'title' => 'Incoming ' . $callTypeText,
            'body' => $callerName . ' is calling you',
        ];

        // FCM v1 data values must be strings
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
        ];

        foreach ($tokens as $token) {
            try {
                if (!$fcm->sendToToken($token, $notification, $data)) {
                    Log::warning('Failed to send call notification to token: ' . substr($token, 0, 20) . '...');
                }
            } catch (\Exception $e) {
                Log::error('Error sending call notification: ' . $e->getMessage());
            }
        }
    }
}
