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
     * Get LiveKit WebSocket URL
     */
    public function getWebSocketUrl(): string
    {
        $url = $this->livekitUrl;
        
        // Ensure URL is properly formatted
        if (empty($url)) {
            $url = 'ws://localhost:7880';
            \Log::warning('LiveKit URL is empty, using default: ' . $url);
        }
        
        // Validate URL format
        if (!preg_match('/^wss?:\/\//', $url)) {
            \Log::warning('LiveKit URL does not start with ws:// or wss://, prepending ws://');
            $url = 'ws://' . ltrim($url, '/');
        }
        
        return $url;
    }
}


