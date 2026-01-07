<?php

namespace App\Services;

use App\Models\TestingMode;
use Illuminate\Support\Facades\Cache;

/**
 * PHASE 2: Testing Mode Service
 * 
 * Manages Testing Mode for allowlisted users with override limits.
 */
class TestingModeService
{
    /**
     * Check if user is in Testing Mode
     */
    public static function isUserInTestingMode(int $userId): bool
    {
        return Cache::remember("testing_mode_user_{$userId}", 60, function () use ($userId) {
            $testingMode = TestingMode::where('is_enabled', true)->first();
            if (!$testingMode) {
                return false;
            }

            // Check if user is in allowlist
            $allowlist = $testingMode->user_ids ?? [];
            return in_array($userId, $allowlist);
        });
    }

    /**
     * Get testing mode limits (overrides Phase limits)
     */
    public static function getTestingLimits(): array
    {
        $testingMode = TestingMode::where('is_enabled', true)->first();
        
        if (!$testingMode) {
            return [];
        }

        return [
            'max_lives' => $testingMode->max_lives ?? 1,
            'max_test_rooms' => $testingMode->max_test_rooms ?? 3,
            'max_test_users' => $testingMode->max_test_users ?? 10,
            'recording_enabled' => false,
            'turn_required' => PhaseModeService::getCurrentPhase() === PhaseModeService::PHASE_ESSENTIAL || PhaseModeService::getCurrentPhase() === PhaseModeService::PHASE_COMFORT,
        ];
    }

    /**
     * Check if feature is available in Testing Mode
     */
    public static function isFeatureAvailableInTesting(string $feature): bool
    {
        $testingMode = TestingMode::where('is_enabled', true)->first();
        if (!$testingMode) {
            return false;
        }

        $features = $testingMode->features ?? [];
        return in_array($feature, $features);
    }
}

