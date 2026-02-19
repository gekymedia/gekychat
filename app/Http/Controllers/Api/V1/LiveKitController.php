<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Generates LiveKit access tokens so mobile/desktop clients can join a room.
 *
 * Requires these values in .env:
 *   LIVEKIT_URL=wss://your-livekit.example.com
 *   LIVEKIT_API_KEY=APIxxxxxxxx
 *   LIVEKIT_API_SECRET=your_secret_here
 *
 * The token is a signed JWT using the LiveKit access-token spec.
 * We implement signing manually to avoid requiring a PHP SDK dependency.
 */
class LiveKitController extends Controller
{
    /**
     * GET /api/v1/calls/livekit-token
     *
     * Query params:
     *   room  – LiveKit room name (e.g. "call_42")
     *   name  – Display name for this participant (optional; defaults to user name)
     */
    public function token(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $room = $request->query('room', 'default_room');
        $name = $request->query('name', $user->name ?? 'User');

        $apiKey    = config('services.livekit.api_key',    env('LIVEKIT_API_KEY'));
        $apiSecret = config('services.livekit.api_secret', env('LIVEKIT_API_SECRET'));
        $url       = config('services.livekit.url',        env('LIVEKIT_URL', 'wss://localhost:7880'));

        if (!$apiKey || !$apiSecret) {
            Log::warning('LiveKit credentials not configured');
            return response()->json(['error' => 'LiveKit not configured on this server'], 503);
        }

        $token = $this->buildAccessToken(
            apiKey:    $apiKey,
            apiSecret: $apiSecret,
            identity:  (string) $user->id,
            name:      $name,
            roomName:  $room,
        );

        return response()->json([
            'url'   => $url,
            'token' => $token,
            'room'  => $room,
        ]);
    }

    /**
     * Build a signed LiveKit access token JWT (HS256).
     *
     * Grant: canPublish + canSubscribe for the specified room.
     */
    private function buildAccessToken(
        string $apiKey,
        string $apiSecret,
        string $identity,
        string $name,
        string $roomName,
    ): string {
        $now = time();

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'iss'   => $apiKey,
            'sub'   => $identity,
            'iat'   => $now,
            'exp'   => $now + 3600, // 1 hour validity
            'nbf'   => $now,
            'name'  => $name,
            'video' => [
                'room'        => $roomName,
                'roomJoin'    => true,
                'canPublish'  => true,
                'canSubscribe' => true,
            ],
        ];

        $headerEncoded  = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        $sigInput       = $headerEncoded . '.' . $payloadEncoded;
        $sig            = hash_hmac('sha256', $sigInput, $apiSecret, true);
        $sigEncoded     = $this->base64UrlEncode($sig);

        return $sigInput . '.' . $sigEncoded;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
