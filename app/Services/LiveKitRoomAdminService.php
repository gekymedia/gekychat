<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Server-side LiveKit Room Service API (Twirp) for host moderation:
 * remove participant, mute microphone track.
 *
 * Requires LIVEKIT_URL (or LIVEKIT_HTTP_URL) and API key/secret.
 */
class LiveKitRoomAdminService
{
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * JWT with roomAdmin for the given room (LiveKit Room Service auth).
     */
    public function buildRoomAdminJwt(string $roomName): string
    {
        $apiKey = config('services.livekit.api_key');
        $apiSecret = config('services.livekit.api_secret');
        if (empty($apiKey) || empty($apiSecret)) {
            throw new RuntimeException('LiveKit is not configured');
        }

        $now = time();
        $payload = [
            'iss' => $apiKey,
            'sub' => 'roomadmin',
            'iat' => $now,
            'exp' => $now + 120,
            'nbf' => $now,
            'video' => [
                'room' => $roomName,
                'roomAdmin' => true,
            ],
        ];

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        $sigInput = $headerEncoded.'.'.$payloadEncoded;
        $sig = hash_hmac('sha256', $sigInput, $apiSecret, true);

        return $sigInput.'.'.$this->base64UrlEncode($sig);
    }

    public function httpBaseUrl(): string
    {
        $explicit = config('services.livekit.http_url');
        if (! empty($explicit)) {
            return rtrim((string) $explicit, '/');
        }

        $ws = (string) config('services.livekit.url', env('LIVEKIT_URL', 'ws://localhost:7880'));
        $ws = trim($ws);
        if (str_starts_with($ws, 'wss://')) {
            return 'https://'.substr($ws, 6);
        }
        if (str_starts_with($ws, 'ws://')) {
            return 'http://'.substr($ws, 5);
        }
        if (str_starts_with($ws, 'https://') || str_starts_with($ws, 'http://')) {
            return rtrim($ws, '/');
        }

        return 'http://localhost:7880';
    }

    private function twirpUrl(string $rpc): string
    {
        return $this->httpBaseUrl().'/twirp/livekit.RoomService/'.$rpc;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $rpc, string $roomName, array $body): array
    {
        $jwt = $this->buildRoomAdminJwt($roomName);
        $url = $this->twirpUrl($rpc);

        $response = Http::timeout(20)
            ->withHeaders([
                'Authorization' => 'Bearer '.$jwt,
                'Content-Type' => 'application/json',
            ])
            ->post($url, $body);

        if ($response->successful()) {
            $json = $response->json();

            return is_array($json) ? $json : [];
        }

        $msg = $response->body();
        Log::warning('LiveKit RoomService request failed', [
            'rpc' => $rpc,
            'status' => $response->status(),
            'body' => $msg,
        ]);

        throw new RuntimeException('LiveKit: '.$msg);
    }

    public function removeParticipant(string $roomName, string $identity): void
    {
        $this->post('RemoveParticipant', $roomName, [
            'room' => $roomName,
            'identity' => $identity,
        ]);
    }

    /**
     * Mute the first published microphone track for this participant.
     */
    public function muteParticipantMicrophone(string $roomName, string $identity): void
    {
        $list = $this->post('ListParticipants', $roomName, [
            'room' => $roomName,
        ]);

        $participants = $list['participants'] ?? [];
        $trackSid = null;

        foreach ($participants as $p) {
            if (($p['identity'] ?? null) !== $identity) {
                continue;
            }
            foreach ($p['tracks'] ?? [] as $track) {
                $type = $track['type'] ?? $track['Type'] ?? '';
                $source = $track['source'] ?? $track['Source'] ?? '';
                $typeStr = is_string($type) ? strtoupper($type) : '';
                $srcStr = is_string($source) ? strtoupper((string) $source) : '';
                // Twirp JSON may use enum names or numeric enums depending on server version.
                $isAudio = $typeStr === 'AUDIO' || $typeStr === '1' || $type === 1;
                $isMic = $srcStr === 'MICROPHONE' || $srcStr === '1' || $source === 1;
                if ($isAudio && $isMic) {
                    $trackSid = $track['sid'] ?? $track['Sid'] ?? $track['track_sid'] ?? null;
                    if (is_string($trackSid) && $trackSid !== '') {
                        break 2;
                    }
                }
            }
        }

        if ($trackSid === null || $trackSid === '') {
            throw new RuntimeException('No microphone track found for participant');
        }

        $this->post('MutePublishedTrack', $roomName, [
            'room' => $roomName,
            'identity' => $identity,
            'trackSid' => $trackSid,
            'muted' => true,
        ]);
    }
}
