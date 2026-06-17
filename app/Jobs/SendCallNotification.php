<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\CallSession;
use App\Support\CallPartyPayload;
use App\Services\ApnsVoipService;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends call FCM after the HTTP response (via afterResponse).
 * Not queued — runs in-process so calls work without a dedicated queue worker.
 */
class SendCallNotification
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

    public function handle(FcmService $fcm, ApnsVoipService $apnsVoip)
    {
        $columns = ['token', 'device_type', 'platform'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('device_tokens', 'voip_token')) {
            $columns[] = 'voip_token';
        }

        $deviceQuery = \App\Models\DeviceToken::where('user_id', $this->callee->id);
        if (\Illuminate\Support\Facades\Schema::hasColumn('device_tokens', 'is_active')) {
            $deviceQuery->where('is_active', true);
        }
        $devices = $deviceQuery->get($columns);

        if ($devices->isEmpty()) {
            Log::info("No FCM tokens found for user {$this->callee->id}");
            return;
        }

        $callTypeText = $this->call->type === 'video' ? 'video call' : 'voice call';
        $callerParty = CallPartyPayload::forUser($this->caller);
        $callerName = $callerParty['name'];

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
            'caller_id' => (string) $callerParty['id'],
            'caller_name' => $callerName,
            'caller_phone' => $callerParty['phone'],
            'caller_avatar' => (string) ($callerParty['avatar'] ?? ''),
            'call_type' => $this->call->type,
            'conversation_id' => (string) ($this->call->conversation_id ?? ''),
            'group_id' => (string) ($this->call->group_id ?? ''),
            'action' => 'incoming_call',
            'priority' => 'high',
            // Include title/body in data for the app to use if needed
            'title' => 'Incoming ' . $callTypeText,
            'body' => $callerName . ' is calling you',
        ];

        foreach ($devices as $device) {
            $deviceType = strtolower((string) ($device->device_type ?? $device->platform ?? 'unknown'));
            $isIos = in_array($deviceType, ['ios', 'iphone', 'ipad', 'apple'], true);

            try {
                // iOS: PushKit VoIP wakes the app in killed state and shows CallKit immediately.
                $voipToken = $device->voip_token ?? null;
                if ($isIos && ! empty($voipToken) && $apnsVoip->isConfigured()) {
                    if ($apnsVoip->sendCallInviteToToken($voipToken, $data)) {
                        continue;
                    }
                    Log::warning('APNS VoIP failed — falling back to FCM for iOS device');
                }

                $token = $device->token;
                if (empty($token) || str_starts_with($token, 'pending-fcm')) {
                    continue;
                }
                if (! $fcm->sendCallInviteToToken($token, $data, $deviceType)) {
                    Log::warning('Failed to send call notification to token: '.substr($token, 0, 20).'...');
                }
            } catch (\Exception $e) {
                Log::error('Error sending call notification: '.$e->getMessage());
            }
        }
    }
}
