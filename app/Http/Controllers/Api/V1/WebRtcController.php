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
     * Get WebRTC TURN/ICE server configuration with resilient multi-tier fallbacks.
     * GET /api/v1/webrtc/config
     *
     * Priority tiers (used in order until connection succeeds in the client):
     *   1. Dedicated self-hosted TURN  (fastest, most reliable)
     *   2. Cloudflare TURN             (anycast, great corporate firewall piercing)
     *   3. Metered.ca TURN             (free-tier fallback, global)
     *   4. Public Google STUN          (last resort — no relay, may fail behind NAT)
     */
    public function getConfig(Request $request)
    {
        $iceServers = $this->buildIceServers();

        return response()->json([
            'ice_servers'      => $iceServers,
            // Convenience aliases kept for older client versions
            'turn_servers'     => array_filter($iceServers, fn($s) => str_contains($s['urls'][0] ?? '', 'turn:')),
            'stun_servers'     => array_filter($iceServers, fn($s) => str_contains($s['urls'][0] ?? '', 'stun:')),
            'ice_transport'    => 'all',          // 'relay' to force TURN only (stricter)
            'bundle_policy'    => 'max-bundle',
            'rtcp_mux_policy'  => 'require',
        ]);
    }

    /**
     * Build a resilient, prioritised ICE server list.
     * All TURN entries include both UDP and TCP transports so TCP-only corporate
     * firewalls that block UDP 3478 can still relay via TCP 443.
     */
    private function buildIceServers(): array
    {
        $servers = [];

        // ── Tier 1: Self-hosted TURN ───────────────────────────────────────
        $turnHost = env('TURN_SERVER_HOST');
        $turnUser = env('TURN_SERVER_USERNAME');
        $turnPass = env('TURN_SERVER_PASSWORD');

        if ($turnHost && $turnUser && $turnPass) {
            // Generate time-limited HMAC credentials (RFC 5389 §10.2)
            $ttl       = 86400; // 24 hours
            $timestamp = time() + $ttl;
            $username  = "{$timestamp}:{$turnUser}";
            $credential = base64_encode(hash_hmac('sha1', $username, $turnPass, true));

            $servers[] = [
                'urls'       => [
                    "turn:{$turnHost}:3478?transport=udp",
                    "turn:{$turnHost}:3478?transport=tcp",
                    "turns:{$turnHost}:443?transport=tcp",  // TURNS over TLS/443
                ],
                'username'   => $username,
                'credential' => $credential,
            ];
        }

        // ── Tier 2: Cloudflare TURN (set CLOUDFLARE_TURN_* in .env) ─────────
        $cfKey    = env('CLOUDFLARE_TURN_KEY_ID');
        $cfSecret = env('CLOUDFLARE_TURN_KEY_API_TOKEN');

        if ($cfKey && $cfSecret) {
            $servers[] = [
                'urls'       => [
                    "turn:turn.cloudflare.com:3478?transport=udp",
                    "turn:turn.cloudflare.com:3478?transport=tcp",
                    "turns:turn.cloudflare.com:5349",
                ],
                'username'   => $cfKey,
                'credential' => $cfSecret,
            ];
        }

        // ── Tier 3: Metered.ca free TURN (set METERED_TURN_* in .env) ───────
        $meteredUser = env('METERED_TURN_USERNAME');
        $meteredPass = env('METERED_TURN_CREDENTIAL');
        $meteredHost = env('METERED_TURN_HOST', 'openrelay.metered.ca');

        if ($meteredUser && $meteredPass) {
            $servers[] = [
                'urls'       => [
                    "turn:{$meteredHost}:80?transport=udp",
                    "turn:{$meteredHost}:80?transport=tcp",
                    "turns:{$meteredHost}:443?transport=tcp",
                ],
                'username'   => $meteredUser,
                'credential' => $meteredPass,
            ];
        }

        // ── Tier 4: Public STUN (no relay; fallback for simple NAT only) ─────
        $servers[] = ['urls' => ['stun:stun.l.google.com:19302']];
        $servers[] = ['urls' => ['stun:stun1.l.google.com:19302']];
        $servers[] = ['urls' => ['stun:stun2.l.google.com:19302']];

        return $servers;
    }

    // Keep old helpers for backward compatibility
    private function getTurnServers(): array { return []; }
    private function getIceServers(): array  { return $this->buildIceServers(); }
    private function getStunServers(): array { return [['url' => 'stun:stun.l.google.com:19302']]; }

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
