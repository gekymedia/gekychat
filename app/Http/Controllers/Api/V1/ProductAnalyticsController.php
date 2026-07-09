<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ProductAnalyticsIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductAnalyticsController extends Controller
{
    public function __construct(
        private ProductAnalyticsIngestService $analytics
    ) {}

    public function startSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_uuid' => 'required|uuid',
            'platform' => 'required|string|max:32',
            'app_version' => 'nullable|string|max:64',
            'device_type' => 'nullable|string|max:64',
            'os_version' => 'nullable|string|max:64',
            'locale' => 'nullable|string|max:16',
        ]);

        $session = $this->analytics->startSession(
            $request->user()->id,
            $data['session_uuid'],
            $data['platform'],
            $data['app_version'] ?? null,
            $data['device_type'] ?? null,
            $data['os_version'] ?? null,
            $data['locale'] ?? null,
        );

        return response()->json([
            'ok' => true,
            'session_uuid' => $session->session_uuid,
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_uuid' => 'required|uuid',
        ]);

        $this->analytics->heartbeat($data['session_uuid'], $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function endSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_uuid' => 'required|uuid',
            'duration_seconds' => 'nullable|integer|min:0',
        ]);

        $this->analytics->endSession(
            $data['session_uuid'],
            $request->user()->id,
            $data['duration_seconds'] ?? null,
        );

        return response()->json(['ok' => true]);
    }

    public function ingestEvents(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_uuid' => 'required|uuid',
            'platform' => 'required|string|max:32',
            'events' => 'required|array|max:100',
            'events.*.event_name' => 'nullable|string|max:64',
            'events.*.name' => 'nullable|string|max:64',
            'events.*.feature_key' => 'nullable|string|max:64',
            'events.*.feature' => 'nullable|string|max:64',
            'events.*.action_key' => 'nullable|string|max:64',
            'events.*.properties' => 'nullable|array',
            'events.*.occurred_at' => 'nullable|date',
        ]);

        $count = $this->analytics->ingestBatch(
            $request->user()->id,
            $data['session_uuid'],
            $data['platform'],
            $data['events'],
        );

        return response()->json(['ok' => true, 'ingested' => $count]);
    }
}
