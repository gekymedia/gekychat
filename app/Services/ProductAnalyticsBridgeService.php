<?php

namespace App\Services;

use App\Models\ProductAnalyticsEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Forwards product analytics events to Amplitude and Firebase GA4 (optional).
 */
class ProductAnalyticsBridgeService
{
    public function isEnabled(): bool
    {
        return $this->amplitudeEnabled() || $this->firebaseEnabled();
    }

    public function forward(ProductAnalyticsEvent $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            if ($this->amplitudeEnabled()) {
                $this->forwardToAmplitude($event);
            }
            if ($this->firebaseEnabled()) {
                $this->forwardToFirebase($event);
            }
        } catch (\Throwable $e) {
            Log::debug('Product analytics bridge failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function amplitudeEnabled(): bool
    {
        return (bool) config('services.product_analytics.amplitude.enabled')
            && config('services.product_analytics.amplitude.api_key');
    }

    private function firebaseEnabled(): bool
    {
        return (bool) config('services.product_analytics.firebase.enabled')
            && config('services.product_analytics.firebase.measurement_id')
            && config('services.product_analytics.firebase.api_secret');
    }

    private function forwardToAmplitude(ProductAnalyticsEvent $event): void
    {
        $eventType = $event->action_key
            ? "action:{$event->action_key}"
            : $event->event_name;

        $props = array_merge(
            is_array($event->properties) ? $event->properties : [],
            array_filter([
                'feature_key' => $event->feature_key,
                'platform' => $event->platform,
                'session_uuid' => $event->session_uuid,
            ])
        );

        Http::timeout(5)->post('https://api2.amplitude.com/2/httpapi', [
            'api_key' => config('services.product_analytics.amplitude.api_key'),
            'events' => [[
                'user_id' => (string) $event->user_id,
                'event_type' => $eventType,
                'time' => $event->occurred_at
                    ? (int) ($event->occurred_at->timestamp * 1000)
                    : (int) (now()->timestamp * 1000),
                'event_properties' => $props,
                'platform' => $event->platform,
            ]],
        ]);
    }

    private function forwardToFirebase(ProductAnalyticsEvent $event): void
    {
        $name = $event->action_key
            ? (preg_replace('/[^a-zA-Z0-9_]/', '_', $event->action_key) ?: 'action')
            : (preg_replace('/[^a-zA-Z0-9_]/', '_', $event->event_name) ?: 'event');

        $params = array_merge(
            is_array($event->properties) ? $event->properties : [],
            array_filter([
                'feature_key' => $event->feature_key,
                'event_name' => $event->event_name,
                'platform' => $event->platform,
            ], fn ($v) => $v !== null && $v !== '')
        );

        // GA4 event param values must be strings or numbers.
        foreach ($params as $k => $v) {
            if (is_bool($v)) {
                $params[$k] = $v ? 'true' : 'false';
            } elseif (is_array($v)) {
                $params[$k] = json_encode($v);
            }
        }

        $measurementId = config('services.product_analytics.firebase.measurement_id');
        $apiSecret = config('services.product_analytics.firebase.api_secret');

        Http::timeout(5)->post(
            "https://www.google-analytics.com/mp/collect?measurement_id={$measurementId}&api_secret={$apiSecret}",
            [
                'client_id' => $event->session_uuid ?: ('user_' . $event->user_id),
                'user_id' => (string) $event->user_id,
                'events' => [[
                    'name' => substr($name, 0, 40),
                    'params' => $params,
                ]],
            ]
        );
    }
}
