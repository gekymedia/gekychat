<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class RealtimeMetricsController extends Controller
{
    public function index(): JsonResponse
    {
        $keys = [
            'rt:inbox:dm:events_total',
            'rt:inbox:dm:recipients_total',
            'rt:inbox:dm:last_duration_ms',
            'rt:inbox:group:events_total',
            'rt:inbox:group:recipients_total',
            'rt:inbox:group:last_duration_ms',
        ];

        $raw = Cache::many($keys);

        $dmEvents = (int) ($raw['rt:inbox:dm:events_total'] ?? 0);
        $dmRecipients = (int) ($raw['rt:inbox:dm:recipients_total'] ?? 0);
        $dmLastDurationMs = (int) ($raw['rt:inbox:dm:last_duration_ms'] ?? 0);

        $groupEvents = (int) ($raw['rt:inbox:group:events_total'] ?? 0);
        $groupRecipients = (int) ($raw['rt:inbox:group:recipients_total'] ?? 0);
        $groupLastDurationMs = (int) ($raw['rt:inbox:group:last_duration_ms'] ?? 0);

        $avgDmRecipients = $dmEvents > 0 ? round($dmRecipients / $dmEvents, 2) : 0.0;
        $avgGroupRecipients = $groupEvents > 0 ? round($groupRecipients / $groupEvents, 2) : 0.0;

        return response()->json([
            'ok' => true,
            'generated_at' => now()->toIso8601String(),
            'metrics' => [
                'dm' => [
                    'events_total' => $dmEvents,
                    'recipients_total' => $dmRecipients,
                    'avg_recipients_per_event' => $avgDmRecipients,
                    'last_fanout_duration_ms' => $dmLastDurationMs,
                    'status' => $dmLastDurationMs > 250 ? 'slow' : 'healthy',
                ],
                'group' => [
                    'events_total' => $groupEvents,
                    'recipients_total' => $groupRecipients,
                    'avg_recipients_per_event' => $avgGroupRecipients,
                    'last_fanout_duration_ms' => $groupLastDurationMs,
                    'status' => $groupLastDurationMs > 350 ? 'slow' : 'healthy',
                ],
            ],
        ]);
    }
}

