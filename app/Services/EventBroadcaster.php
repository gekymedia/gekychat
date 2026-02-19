<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * ✅ MODERN: Centralized event metadata for real-time broadcasts
 * Use envelope() in Event::broadcastWith() for consistent event_id, timestamp, version
 */
class EventBroadcaster
{
    public const VERSION = 'v1';

    /** Return metadata to merge into any broadcast payload */
    public static function envelope(): array
    {
        return [
            'event_id' => Str::uuid()->toString(),
            'timestamp' => now()->toIso8601String(),
            'version' => self::VERSION,
        ];
    }
}
