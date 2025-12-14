<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    /**
     * Meta / WhatsApp-style webhook verification (GET)
     *
     * ?hub.mode=subscribe
     * &hub.challenge=123456
     * &hub.verify_token=YOUR_TOKEN
     */
    public function verify(Request $request)
    {
        $verifyToken = config('platform.webhook_verify_token');

        if (
            $request->query('hub_mode') === 'subscribe' &&
            $request->query('hub_verify_token') === $verifyToken
        ) {
            return response($request->query('hub_challenge'), 200);
        }

        return response('Invalid verification token', 403);
    }

    /**
     * Receive webhook events (POST)
     */
    public function handle(Request $request)
    {
        // Optional: validate signature
        if (!$this->isValidSignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();

        Log::info('[PLATFORM WEBHOOK]', $payload);

        /*
        |--------------------------------------------------------------------------
        | Event Router
        |--------------------------------------------------------------------------
        | Decide what to do based on event type
        */
        match ($payload['event'] ?? null) {
            'message.received' => $this->onMessageReceived($payload),
            'message.delivered' => $this->onMessageDelivered($payload),
            'message.read' => $this->onMessageRead($payload),
            'group.joined' => $this->onGroupJoined($payload),
            'group.left' => $this->onGroupLeft($payload),
            default => Log::warning('[PLATFORM WEBHOOK] Unknown event', $payload),
        };

        return response()->json(['ok' => true]);
    }

    /* ---------------------------------------------------------------------- */
    /*                            EVENT HANDLERS                                */
    /* ---------------------------------------------------------------------- */

    protected function onMessageReceived(array $data): void
    {
        // Example payload
        // message: { id, from, to, body, attachments, timestamp }
        Log::info('[WEBHOOK] Message received', $data['message'] ?? []);
    }

    protected function onMessageDelivered(array $data): void
    {
        Log::info('[WEBHOOK] Message delivered', $data);
    }

    protected function onMessageRead(array $data): void
    {
        Log::info('[WEBHOOK] Message read', $data);
    }

    protected function onGroupJoined(array $data): void
    {
        Log::info('[WEBHOOK] Group joined', $data);
    }

    protected function onGroupLeft(array $data): void
    {
        Log::info('[WEBHOOK] Group left', $data);
    }

    /* ---------------------------------------------------------------------- */
    /*                          SECURITY CHECK                                  */
    /* ---------------------------------------------------------------------- */

    protected function isValidSignature(Request $request): bool
    {
        $secret = config('platform.webhook_secret');

        if (!$secret) {
            return true; // allow if not configured
        }

        $signature = $request->header('X-Gekychat-Signature');

        if (!$signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
