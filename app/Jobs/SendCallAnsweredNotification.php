<?php

namespace App\Jobs;

use App\Models\CallSession;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Data-only FCM to the caller when the callee POSTs join-call — backup when Pusher
 * is down or the caller app is backgrounded without a live WebSocket.
 */
class SendCallAnsweredNotification
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public User $caller;
    public CallSession $call;

    public function __construct(User $caller, CallSession $call)
    {
        $this->caller = $caller;
        $this->call = $call;
    }

    public function handle(FcmService $fcm): void
    {
        $deviceQuery = \App\Models\DeviceToken::where('user_id', $this->caller->id);
        if (Schema::hasColumn('device_tokens', 'is_active')) {
            $deviceQuery->where('is_active', true);
        }
        $devices = $deviceQuery->get(['token', 'device_type', 'platform']);

        if ($devices->isEmpty()) {
            Log::info("No device tokens for call-answered caller {$this->caller->id}");

            return;
        }

        $data = [
            'type' => 'call_answered',
            'action' => 'callee_joined',
            'signal_type' => 'livekit-joined',
            'call_id' => (string) $this->call->id,
            'session_id' => (string) $this->call->id,
            'call_type' => (string) ($this->call->type ?? 'voice'),
            'priority' => 'high',
        ];

        if ($this->call->conversation_id) {
            $data['conversation_id'] = (string) $this->call->conversation_id;
        }
        if ($this->call->group_id) {
            $data['group_id'] = (string) $this->call->group_id;
        }

        $collapseKey = 'call_answered_' . $this->call->id;

        foreach ($devices as $device) {
            $token = $device->token ?? '';
            if ($token === '' || str_starts_with($token, 'pending-fcm')) {
                continue;
            }

            try {
                if (! $fcm->sendCallAnsweredToToken($token, $data, $collapseKey)) {
                    Log::warning(
                        'Failed to send call-answered to token: ' . substr($token, 0, 20) . '...'
                    );
                }
            } catch (\Exception $e) {
                Log::error('Error sending call-answered notification: ' . $e->getMessage());
            }
        }
    }
}
