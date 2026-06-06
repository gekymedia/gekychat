<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebPushController extends Controller
{
    /**
     * Register a browser Web Push subscription (VAPID).
     * POST /web-push/subscribe
     */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys' => ['required', 'array'],
            'keys.auth' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
        ]);

        $user = $request->user();
        $user->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['contentEncoding'] ?? 'aesgcm'
        );

        return response()->json(['success' => true]);
    }

    /**
     * Remove a browser Web Push subscription.
     * DELETE /web-push/subscribe
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json(['success' => true]);
    }

    /**
     * VAPID public key for PushManager.subscribe() on the client.
     * GET /web-push/vapid-public-key
     */
    public function vapidPublicKey(): JsonResponse
    {
        $key = config('webpush.vapid.public_key');

        if (! $key) {
            return response()->json(['public_key' => null], 404);
        }

        return response()->json(['public_key' => $key]);
    }
}
