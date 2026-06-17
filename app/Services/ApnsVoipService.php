<?php

namespace App\Services;

use App\Support\CallKitUuid;
use Illuminate\Support\Facades\Log;

/**
 * Sends Apple PushKit VoIP notifications (HTTP/2 + JWT). Wakes iOS for incoming calls
 * when the app is killed — required for reliable CallKit in terminated state.
 */
class ApnsVoipService
{
    private ?string $cachedJwt = null;
    private int $cachedJwtExpiresAt = 0;

    public function isConfigured(): bool
    {
        return (bool) config('services.apns.key_id')
            && (bool) config('services.apns.team_id')
            && (bool) config('services.apns.bundle_id')
            && is_readable($this->keyPath());
    }

    private function keyPath(): string
    {
        $path = (string) config('services.apns.key_path');

        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    /**
     * @param  array<string, mixed>  $callData  Keys from SendCallNotification $data
     */
    public function sendCallInviteToToken(string $voipToken, array $callData): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('APNS VoIP not configured — skipping VoIP push');

            return false;
        }

        $sessionId = (int) ($callData['session_id'] ?? $callData['call_id'] ?? 0);
        if ($sessionId <= 0) {
            return false;
        }

        $callType = strtolower((string) ($callData['call_type'] ?? 'voice'));
        $callerName = (string) ($callData['caller_name'] ?? 'Someone');
        $handle = (string) ($callData['caller_phone'] ?? $callData['caller_id'] ?? $callerName);

        $payload = [
            'id' => CallKitUuid::forCallSession($sessionId),
            'nameCaller' => $callerName,
            'handle' => $handle,
            'isVideo' => $callType === 'video',
            'session_id' => (string) $sessionId,
            'call_id' => (string) $sessionId,
            'caller_id' => (string) ($callData['caller_id'] ?? ''),
            'caller_name' => $callerName,
            'caller_avatar' => (string) ($callData['caller_avatar'] ?? ''),
            'call_type' => $callType,
            'type' => 'call_invite',
            'conversation_id' => (string) ($callData['conversation_id'] ?? ''),
            'group_id' => (string) ($callData['group_id'] ?? ''),
        ];

        return $this->sendVoipPush($voipToken, $payload);
    }

    /**
     * Dismiss CallKit on iOS devices that received the invite via PushKit VoIP.
     *
     * @param  array<string, mixed>  $callData
     */
    public function sendCallCancelToToken(string $voipToken, array $callData): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $sessionId = (int) ($callData['session_id'] ?? $callData['call_id'] ?? 0);
        if ($sessionId <= 0) {
            return false;
        }

        $payload = [
            'id' => CallKitUuid::forCallSession($sessionId),
            'type' => 'call_cancel',
            'action' => 'cancel',
            'session_id' => (string) $sessionId,
            'call_id' => (string) $sessionId,
            'reason' => (string) ($callData['reason'] ?? 'answered_elsewhere'),
        ];

        return $this->sendVoipPush($voipToken, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendVoipPush(string $deviceToken, array $payload): bool
    {
        $jwt = $this->getJwt();
        if ($jwt === null) {
            return false;
        }

        $host = config('services.apns.production', true)
            ? 'https://api.push.apple.com'
            : 'https://api.sandbox.push.apple.com';

        $bundleId = (string) config('services.apns.bundle_id');
        $url = $host.'/3/device/'.$deviceToken;

        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'authorization: bearer '.$jwt,
                'apns-topic: '.$bundleId.'.voip',
                'apns-push-type: voip',
                'apns-priority: 10',
                'apns-expiration: 0',
                'content-type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        Log::warning('APNS VoIP push failed', [
            'http_code' => $httpCode,
            'response' => $response,
            'curl_error' => $curlError,
            'token_prefix' => substr($deviceToken, 0, 12),
        ]);

        return false;
    }

    private function getJwt(): ?string
    {
        if ($this->cachedJwt !== null && time() < $this->cachedJwtExpiresAt - 60) {
            return $this->cachedJwt;
        }

        $keyId = (string) config('services.apns.key_id');
        $teamId = (string) config('services.apns.team_id');
        $privateKey = openssl_pkey_get_private(file_get_contents($this->keyPath()));
        if ($privateKey === false) {
            Log::error('APNS VoIP: invalid .p8 key');

            return null;
        }

        $header = $this->base64UrlEncode(json_encode(['alg' => 'ES256', 'kid' => $keyId]));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $teamId,
            'iat' => time(),
        ]));
        $unsigned = $header.'.'.$claims;

        $signature = '';
        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            Log::error('APNS VoIP: JWT sign failed');

            return null;
        }

        // ES256 requires raw R||S (64 bytes), not DER
        $signature = $this->derToJose($signature);
        if ($signature === null) {
            Log::error('APNS VoIP: JWT signature conversion failed');

            return null;
        }

        $this->cachedJwt = $unsigned.'.'.$this->base64UrlEncode($signature);
        $this->cachedJwtExpiresAt = time() + 3500;

        return $this->cachedJwt;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Convert openssl ECDSA DER signature to JOSE raw format.
     */
    private function derToJose(string $der): ?string
    {
        $pos = 0;
        if (! isset($der[$pos]) || ord($der[$pos++]) !== 0x30) {
            return null;
        }
        $this->readAsn1Length($der, $pos);
        if (! isset($der[$pos]) || ord($der[$pos++]) !== 0x02) {
            return null;
        }
        $rLen = $this->readAsn1Length($der, $pos);
        $r = substr($der, $pos, $rLen);
        $pos += $rLen;
        if (! isset($der[$pos]) || ord($der[$pos++]) !== 0x02) {
            return null;
        }
        $sLen = $this->readAsn1Length($der, $pos);
        $s = substr($der, $pos, $sLen);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

        return $r.$s;
    }

    private function readAsn1Length(string $data, int &$pos): int
    {
        $len = ord($data[$pos++]);
        if ($len & 0x80) {
            $num = $len & 0x1f;
            $len = 0;
            for ($i = 0; $i < $num; $i++) {
                $len = ($len << 8) | ord($data[$pos++]);
            }
        }

        return $len;
    }
}
