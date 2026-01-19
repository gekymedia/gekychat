<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * WebRTC Configuration Controller
 * Provides TURN/ICE server configuration for WebRTC calls
 */
class WebRtcController extends Controller
{
    /**
     * Get WebRTC TURN/ICE server configuration
     * GET /api/v1/webrtc/config
     */
    public function getConfig(Request $request)
    {
        $user = $request->user();

        // Get TURN server configuration from environment or config
        $turnServers = $this->getTurnServers();
        $iceServers = $this->getIceServers();

        return response()->json([
            'turn_servers' => $turnServers,
            'ice_servers' => $iceServers,
            'stun_servers' => $this->getStunServers(),
        ]);
    }

    /**
     * Get TURN server configuration
     */
    private function getTurnServers(): array
    {
        $servers = [];

        // Primary TURN server from config
        $turnUrl = config('services.webrtc.turn_url') ?? env('TURN_SERVER_URL');
        $turnUsername = config('services.webrtc.turn_username') ?? env('TURN_SERVER_USERNAME');
        $turnPassword = config('services.webrtc.turn_password') ?? env('TURN_SERVER_PASSWORD');

        if ($turnUrl && $turnUsername && $turnPassword) {
            $servers[] = [
                'urls' => [$turnUrl],
                'username' => $turnUsername,
                'credential' => $turnPassword,
            ];
        }

        // Add public TURN servers as fallback (optional)
        $publicTurnServers = config('services.webrtc.public_turn_servers', []);
        foreach ($publicTurnServers as $server) {
            $servers[] = [
                'urls' => [$server['url']],
                'username' => $server['username'] ?? null,
                'credential' => $server['credential'] ?? null,
            ];
        }

        return $servers;
    }

    /**
     * Get ICE server configuration
     */
    private function getIceServers(): array
    {
        $iceServers = [];

        // Add STUN servers
        $stunServers = $this->getStunServers();
        foreach ($stunServers as $stun) {
            $iceServers[] = [
                'urls' => [$stun['url']],
            ];
        }

        // Add TURN servers
        $turnServers = $this->getTurnServers();
        foreach ($turnServers as $turn) {
            $iceServers[] = [
                'urls' => $turn['urls'],
                'username' => $turn['username'],
                'credential' => $turn['credential'],
            ];
        }

        return $iceServers;
    }

    /**
     * Get STUN server configuration
     */
    private function getStunServers(): array
    {
        $servers = [];

        // Primary STUN server from config
        $stunUrl = config('services.webrtc.stun_url') ?? env('STUN_SERVER_URL', 'stun:stun.l.google.com:19302');

        if ($stunUrl) {
            $servers[] = [
                'url' => $stunUrl,
            ];
        }

        // Add public STUN servers as fallback
        $publicStunServers = [
            'stun:stun.l.google.com:19302',
            'stun:stun1.l.google.com:19302',
            'stun:stun2.l.google.com:19302',
        ];

        foreach ($publicStunServers as $stun) {
            if (!in_array($stun, array_column($servers, 'url'))) {
                $servers[] = [
                    'url' => $stun,
                ];
            }
        }

        return $servers;
    }
}
