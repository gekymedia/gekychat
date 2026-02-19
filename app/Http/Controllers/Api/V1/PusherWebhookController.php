<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Pusher webhook for connection/disconnection monitoring (Phase 11).
 * Configure in Pusher Dashboard: Webhooks → Add endpoint to this URL.
 */
class PusherWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $payload = $request->all();
        $events = $payload['events'] ?? [];

        foreach ($events as $event) {
            $name = $event['name'] ?? '';
            if (in_array($name, ['channel_occupied', 'channel_vacated', 'member_added', 'member_removed'], true)) {
                Log::channel('single')->info('Pusher webhook', [
                    'event' => $name,
                    'channel' => $event['channel'] ?? null,
                    'time_ms' => $event['time_ms'] ?? null,
                ]);
            }
        }

        return response()->json(['ok' => true], 200);
    }
}
