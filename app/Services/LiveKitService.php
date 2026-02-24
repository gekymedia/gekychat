<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * PHASE 2: LiveKit Service
 * 
 * Handles LiveKit JWT token generation and room management.
 * All business logic is enforced in Laravel before token issuance.
 */
class LiveKitService
{
    private string $apiKey;
    private string $apiSecret;
    private string $livekitUrl;

    public function __construct()
    {
        $this->apiKey = config('services.livekit.api_key');
        $this->apiSecret = config('services.livekit.api_secret');
        $this->livekitUrl = config('services.livekit.url', 'ws://localhost:7880');
        
        // Validate configuration
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            \Log::warning('LiveKit API key or secret is not configured. Live broadcasting will not work.');
        }
        
        if (empty($this->livekitUrl) || $this->livekitUrl === 'ws://localhost:7880') {
            \Log::warning('LiveKit URL is not configured or using default localhost. Live broadcasting may not work in production.');
        }
    }

    /**
     * Generate LiveKit JWT token for a user to join a room
     * 
     * @param int $userId
     * @param string $roomName
     * @param string $identity User's identity (username or user ID)
     * @param array $grants Permissions (canPublish, canSubscribe, etc.)
     * @return string JWT token
     */
    public function generateToken(int $userId, string $roomName, string $identity, array $grants = []): string
    {
        // Default grants
        $defaultGrants = [
            'canPublish' => false,
            'canSubscribe' => true,
            'canUpdateOwnMetadata' => true,
        ];

        $grants = array_merge($defaultGrants, $grants);

        // Build JWT payload
        $now = Carbon::now()->timestamp;
        $expiresIn = $grants['expiresIn'] ?? 3600; // Default 1 hour

        // Build JWT payload according to LiveKit format
        // Reference: https://docs.livekit.io/home/security/access-tokens/
        $payload = [
            'iss' => $this->apiKey,
            'sub' => $identity,
            'nbf' => $now,
            'exp' => $now + $expiresIn,
            'video' => [
                'room' => $roomName,
                'roomJoin' => true,
                'canPublish' => $grants['canPublish'] ?? false,
                'canSubscribe' => $grants['canSubscribe'] ?? true,
                'canUpdateOwnMetadata' => $grants['canUpdateOwnMetadata'] ?? true,
                'canPublishData' => $grants['canPublishData'] ?? false,
                'hidden' => $grants['hidden'] ?? false,
                'recorder' => $grants['recorder'] ?? false,
            ],
        ];
        
        // Log for debugging (remove in production if needed)
        if (config('app.debug')) {
            \Log::debug('LiveKit JWT payload', [
                'api_key' => $this->apiKey,
                'identity' => $identity,
                'room' => $roomName,
                'grants' => $grants,
            ]);
        }

        // Generate JWT token
        return $this->generateJwt($payload);
    }

    /**
     * Generate JWT token using LiveKit's format
     */
    private function generateJwt(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', "$encodedHeader.$encodedPayload", $this->apiSecret, true);
        $encodedSignature = $this->base64UrlEncode($signature);

        return "$encodedHeader.$encodedPayload.$encodedSignature";
    }

    /**
     * Base64 URL encode (without padding)
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Get LiveKit WebSocket URL (must be reachable from the browser).
     * When the Laravel app is served over HTTPS, returns wss:// so the browser does not block mixed content.
     */
    public function getWebSocketUrl(): string
    {
        $url = trim((string) $this->livekitUrl);
        
        if ($url === '') {
            $url = 'ws://localhost:7880';
            Log::warning('LiveKit URL is empty, using default. Set LIVEKIT_URL in .env to your LiveKit server (e.g. wss://livekit.example.com).');
        }
        
        $url = rtrim($url, '/');
        
        if (!preg_match('/^wss?:\/\//', $url)) {
            Log::warning('LiveKit URL does not start with ws:// or wss://, prepending ws://', ['url' => $url]);
            $url = 'ws://' . ltrim($url, '/');
        }
        
        $appUrl = config('app.url', '');
        if (str_starts_with($url, 'ws://') && str_starts_with($appUrl, 'https://')) {
            $url = 'wss://' . substr($url, 5);
            Log::info('LiveKit: upgraded WebSocket URL to WSS for HTTPS app', ['websocket_url' => $url]);
        }
        
        if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
            Log::warning('LiveKit URL points to localhost. Browsers will connect to the user\'s machine, not the server. Set LIVEKIT_URL to your public LiveKit URL (e.g. wss://livekit.yourdomain.com).');
        }
        
        Log::info('LiveKit getWebSocketUrl', ['url' => $url, 'app_url' => $appUrl]);
        return $url;
    }
}


