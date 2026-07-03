<?php

namespace App\Jobs;

use App\Models\CallSession;
use App\Models\User;
use App\Services\ApnsVoipService;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Data-only FCM (and iOS VoIP) to stop ringing on a user's other devices when they
 * answered on one device. Mirrors SendCallNotification for background/killed clients.
 */
class SendCallCancelNotification
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public User $user;
    public CallSession $call;
    /**
     * installation_id of the device that just answered — excluded below so the
     * "stop ringing on other devices" cancel doesn't also tear down this same
     * device's just-accepted call.
     */
    public ?string $excludeInstallationId;
    public ?string $excludeDeviceId;

    public function __construct(
        User $user,
        CallSession $call,
        ?string $excludeInstallationId = null,
        ?string $excludeDeviceId = null,
    ) {
        $this->user = $user;
        $this->call = $call;
        $this->excludeInstallationId = $excludeInstallationId;
        $this->excludeDeviceId = $excludeDeviceId;
    }

    public function handle(FcmService $fcm, ApnsVoipService $apnsVoip): void
    {
        $columns = ['token', 'device_type', 'platform'];
        if (Schema::hasColumn('device_tokens', 'voip_token')) {
            $columns[] = 'voip_token';
        }
        $hasInstallationColumn = Schema::hasColumn('device_tokens', 'installation_id');
        if ($hasInstallationColumn) {
            $columns[] = 'installation_id';
        }
        $hasDeviceIdColumn = Schema::hasColumn('device_tokens', 'device_id');
        if ($hasDeviceIdColumn) {
            $columns[] = 'device_id';
        }

        $deviceQuery = \App\Models\DeviceToken::where('user_id', $this->user->id);
        if (Schema::hasColumn('device_tokens', 'is_active')) {
            $deviceQuery->where('is_active', true);
        }
        if ($hasInstallationColumn && ! empty($this->excludeInstallationId)) {
            $deviceQuery->where(function ($q) {
                $q->whereNull('installation_id')
                    ->orWhere('installation_id', '!=', $this->excludeInstallationId);
            });
        } elseif ($hasDeviceIdColumn && ! empty($this->excludeDeviceId)) {
            $deviceQuery->where(function ($q) {
                $q->whereNull('device_id')
                    ->orWhere('device_id', '!=', $this->excludeDeviceId);
            });
        }
        $devices = $deviceQuery->get($columns);

        if ($devices->isEmpty()) {
            Log::info("No device tokens for call-cancel user {$this->user->id}");

            return;
        }

        $data = [
            'type' => 'call_cancel',
            'action' => 'cancel',
            'call_id' => (string) $this->call->id,
            'session_id' => (string) $this->call->id,
            'reason' => 'answered_elsewhere',
            'priority' => 'high',
        ];

        $collapseKey = 'call_cancel_' . $this->call->id;

        foreach ($devices as $device) {
            $deviceType = strtolower((string) ($device->device_type ?? $device->platform ?? 'unknown'));
            $isIos = in_array($deviceType, ['ios', 'iphone', 'ipad', 'apple'], true);

            try {
                $voipToken = $device->voip_token ?? null;
                if ($isIos && ! empty($voipToken) && $apnsVoip->isConfigured()) {
                    $apnsVoip->sendCallCancelToToken($voipToken, $data);
                }

                $token = $device->token;
                if (empty($token) || str_starts_with($token, 'pending-fcm')) {
                    continue;
                }
                if (! $fcm->sendCallCancelToToken($token, $data, $collapseKey)) {
                    Log::warning('Failed to send call-cancel to token: ' . substr($token, 0, 20) . '...');
                }
            } catch (\Exception $e) {
                Log::error('Error sending call-cancel notification: ' . $e->getMessage());
            }
        }
    }
}
