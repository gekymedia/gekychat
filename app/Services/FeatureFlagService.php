<?php

namespace App\Services;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Feature Flag Service
 * 
 * PHASE 0: Foundation service for feature flags.
 * This service provides a clean way to check if features are enabled.
 * 
 * Usage:
 *   FeatureFlagService::isEnabled('stealth_status', $user)
 *   FeatureFlagService::isEnabled('delete_for_everyone', $user, 'mobile')
 * 
 * TODO (PHASE 1): Wire this into controllers/services where features need gating
 * TODO (PHASE 1): Add admin UI for managing feature flags
 * TODO (PHASE 2): Add per-user/per-plan conditions
 */
class FeatureFlagService
{
    /**
     * Check if a feature is enabled for a user/platform
     * 
     * @param string $key Feature flag key (e.g., 'stealth_status')
     * @param User|null $user Optional user for per-user conditions
     * @param string|null $platform Optional platform ('web', 'mobile', 'desktop')
     * @return bool
     */
    public static function isEnabled(string $key, ?User $user = null, ?string $platform = null): bool
    {
        $cacheKey = "feature_flag:{$key}:" . ($user?->id ?? 'all') . ':' . ($platform ?? 'all');
        
        return Cache::remember($cacheKey, 3600, function () use ($key, $user, $platform) {
            $flag = FeatureFlag::where('key', $key)->first();
            
            if (!$flag || !$flag->enabled) {
                return false;
            }
            
            // Check platform
            if ($platform && $flag->platform !== 'all' && $flag->platform !== $platform) {
                return false;
            }
            
            // TODO (PHASE 2): Check per-user conditions from $flag->conditions
            // e.g., beta_users, premium_only, etc.
            
            return true;
        });
    }
    
    /**
     * Clear feature flag cache (useful when updating flags)
     */
    public static function clearCache(string $key): void
    {
        Cache::forget("feature_flag:{$key}:*");
    }
    
    /**
     * Get all enabled feature flags for a platform
     * 
     * @param string|null $platform
     * @return array
     */
    public static function getEnabled(string $platform = null): array
    {
        $query = FeatureFlag::where('enabled', true);
        
        if ($platform) {
            $query->where(function ($q) use ($platform) {
                $q->where('platform', $platform)
                  ->orWhere('platform', 'all');
            });
        }
        
        return $query->pluck('key')->toArray();
    }
}


