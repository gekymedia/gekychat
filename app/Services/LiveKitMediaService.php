<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * LiveKit Egress + Ingress Twirp API (recording, RTMP out, RTMP/WHIP in).
 */
class LiveKitMediaService
{
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, mixed>  $videoGrants
     */
    private function buildJwt(array $videoGrants, string $sub = 'media-admin'): string
    {
        $apiKey = config('services.livekit.api_key');
        $apiSecret = config('services.livekit.api_secret');
        if (empty($apiKey) || empty($apiSecret)) {
            throw new RuntimeException('LiveKit is not configured');
        }

        $now = time();
        $payload = [
            'iss' => $apiKey,
            'sub' => $sub,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 600,
            'video' => $videoGrants,
        ];

        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = $this->base64UrlEncode(json_encode($payload));
        $sigInput = $header.'.'.$body;
        $sig = $this->base64UrlEncode(hash_hmac('sha256', $sigInput, $apiSecret, true));

        return $sigInput.'.'.$sig;
    }

    private function httpBaseUrl(): string
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

        return 'http://127.0.0.1:7880';
    }

    /**
     * Prefer loopback HTTP for server-side Twirp (avoids TLS hairpin).
     */
    private function twirpBaseUrl(): string
    {
        $internal = config('services.livekit.internal_http_url');
        if (! empty($internal)) {
            return rtrim((string) $internal, '/');
        }

        // Same host as LiveKit SFU
        return 'http://127.0.0.1:7880';
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $service, string $rpc, array $videoGrants, array $body): array
    {
        $jwt = $this->buildJwt($videoGrants);
        $url = $this->twirpBaseUrl().'/twirp/livekit.'.$service.'/'.$rpc;

        $response = Http::timeout(60)
            ->withHeaders([
                'Authorization' => 'Bearer '.$jwt,
                'Content-Type' => 'application/json',
            ])
            ->post($url, $body);

        if ($response->successful()) {
            $json = $response->json();

            return is_array($json) ? $json : [];
        }

        Log::warning('LiveKit media API failed', [
            'service' => $service,
            'rpc' => $rpc,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new RuntimeException('LiveKit '.$service.'/'.$rpc.': '.$response->body());
    }

    /**
     * Start room-composite recording to local MP4 (mounted recordings volume).
     *
     * @return array<string, mixed>
     */
    public function startRoomRecording(string $roomName, ?string $filepath = null): array
    {
        $filepath = $filepath ?: '/out/recordings/'.$roomName.'-{time}.mp4';

        return $this->post('Egress', 'StartRoomCompositeEgress', [
            'roomRecord' => true,
        ], [
            'room_name' => $roomName,
            'layout' => 'speaker',
            'audio_only' => false,
            'file_outputs' => [
                [
                    'filepath' => $filepath,
                ],
            ],
        ]);
    }

    /**
     * Stream a room out to an external RTMP URL (YouTube, Twitch, custom).
     *
     * @return array<string, mixed>
     */
    public function startRoomRtmpOut(string $roomName, string $rtmpUrl): array
    {
        return $this->post('Egress', 'StartRoomCompositeEgress', [
            'roomRecord' => true,
        ], [
            'room_name' => $roomName,
            'layout' => 'speaker',
            'stream_outputs' => [
                [
                    'protocol' => 'RTMP',
                    'urls' => [$rtmpUrl],
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function stopEgress(string $egressId): array
    {
        return $this->post('Egress', 'StopEgress', [
            'roomRecord' => true,
        ], [
            'egress_id' => $egressId,
        ]);
    }

    /**
     * Create RTMP ingress for OBS / hardware encoders into a LiveKit room.
     *
     * @return array<string, mixed>
     */
    public function createRtmpIngress(string $roomName, string $name, string $identity = 'ingress-obs'): array
    {
        return $this->post('Ingress', 'CreateIngress', [
            'ingressAdmin' => true,
        ], [
            'input_type' => 'RTMP_INPUT',
            'name' => $name,
            'room_name' => $roomName,
            'participant_identity' => $identity,
            'participant_name' => $name,
            'enable_transcoding' => true,
        ]);
    }

    /**
     * Create WHIP ingress for modern browsers / WHIP clients.
     *
     * @return array<string, mixed>
     */
    public function createWhipIngress(string $roomName, string $name, string $identity = 'ingress-whip'): array
    {
        return $this->post('Ingress', 'CreateIngress', [
            'ingressAdmin' => true,
        ], [
            'input_type' => 'WHIP_INPUT',
            'name' => $name,
            'room_name' => $roomName,
            'participant_identity' => $identity,
            'participant_name' => $name,
            'enable_transcoding' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteIngress(string $ingressId): array
    {
        return $this->post('Ingress', 'DeleteIngress', [
            'ingressAdmin' => true,
        ], [
            'ingress_id' => $ingressId,
        ]);
    }
}
