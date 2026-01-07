<?php

namespace App\Services;

use App\Models\PhaseMode;
use Illuminate\Support\Facades\Cache;

/**
 * PHASE 2: Phase Mode Service
 * 
 * Manages Phase Mode configuration (BASIC, ESSENTIAL, COMFORT) and enforces limits.
 */
class PhaseModeService
{
    const PHASE_BASIC = 'basic';
    const PHASE_ESSENTIAL = 'essential';
    const PHASE_COMFORT = 'comfort';

    /**
     * Get current active Phase Mode
     */
    public static function getCurrentPhase(): string
    {
        return Cache::remember('phase_mode', 60, function () {
            $phase = PhaseMode::where('is_active', true)->first();
            return $phase ? $phase->name : self::PHASE_BASIC;
        });
    }

    /**
     * Check if feature is allowed in current phase
     */
    public static function isFeatureAllowed(string $feature): bool
    {
        $phase = self::getCurrentPhase();
        
        $phaseRules = [
            self::PHASE_BASIC => [
                'live_broadcast' => false,
                'group_video' => false,
                'group_audio' => true, // Limited
                'recording' => false,
                'turn_required' => false,
            ],
            self::PHASE_ESSENTIAL => [
                'live_broadcast' => true, // Max 1 concurrent
                'group_video' => true,
                'group_audio' => true,
                'recording' => false,
                'turn_required' => false,
            ],
            self::PHASE_COMFORT => [
                'live_broadcast' => true, // Max 3 concurrent
                'group_video' => true,
                'group_audio' => true,
                'recording' => true,
                'turn_required' => true,
            ],
        ];

        return $phaseRules[$phase][$feature] ?? false;
    }

    /**
     * Get max concurrent live broadcasts for current phase
     */
    public static function getMaxConcurrentLives(): int
    {
        $phase = self::getCurrentPhase();
        
        $limits = [
            self::PHASE_BASIC => 0,
            self::PHASE_ESSENTIAL => 1,
            self::PHASE_COMFORT => 3,
        ];

        return $limits[$phase] ?? 0;
    }

    /**
     * Get max group call participants for current phase
     */
    public static function getMaxGroupCallParticipants(): int
    {
        $phase = self::getCurrentPhase();
        
        $limits = [
            self::PHASE_BASIC => 6,
            self::PHASE_ESSENTIAL => 10,
            self::PHASE_COMFORT => 20,
        ];

        return $limits[$phase] ?? 6;
    }

    /**
     * Check if recording is enabled
     */
    public static function isRecordingEnabled(): bool
    {
        return self::isFeatureAllowed('recording');
    }

    /**
     * Check if TURN is required
     */
    public static function isTurnRequired(): bool
    {
        return self::isFeatureAllowed('turn_required');
    }
}


