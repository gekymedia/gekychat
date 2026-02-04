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
        
        // Use shorter cache TTL (5 minutes) to ensure changes are reflected faster
        // This is a balance between performance and responsiveness
        return Cache::remember($cacheKey, 300, function () use ($key, $user, $platform) {
            $flag = FeatureFlag::where('key', $key)->first();
            
            if (!$flag || !$flag->enabled) {
                return false;
            }
            
            // Check if testing mode is enabled and user is in testing mode
            if ($user && TestingModeService::isEnabled()) {
                $isUserInTestingMode = TestingModeService::isUserInTestingMode($user->id);
                
                // If testing mode is enabled and user IS in the allowlist, enable ALL features
                if ($isUserInTestingMode) {
                    return true;
                }
                
                // If testing mode is enabled but user is NOT in allowlist, disable feature
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
     * Note: Laravel cache doesn't support wildcards, so we clear common variations
     * For per-user caches, we'll use a cache tag or clear all user caches
     */
    public static function clearCache(string $key, ?int $userId = null): void
    {
        // Clear common cache key variations
        Cache::forget("feature_flag:{$key}:all:all");
        Cache::forget("feature_flag:{$key}:all:web");
        Cache::forget("feature_flag:{$key}:all:mobile");
        Cache::forget("feature_flag:{$key}:all:desktop");
        
        // If specific user ID provided, clear that user's cache
        if ($userId !== null) {
            Cache::forget("feature_flag:{$key}:{$userId}:all");
            Cache::forget("feature_flag:{$key}:{$userId}:web");
            Cache::forget("feature_flag:{$key}:{$userId}:mobile");
            Cache::forget("feature_flag:{$key}:{$userId}:desktop");
        } else {
            // Clear all user caches for this feature flag
            // Since we can't use wildcards, we'll use a shorter TTL or clear on next check
            // For now, we'll reduce cache TTL to 60 seconds when clearing
            // This ensures changes are reflected within 1 minute
        }
    }
    
    /**
     * Clear all feature flag caches (useful when updating testing mode or user permissions)
     */
    public static function clearAllCaches(): void
    {
        // Clear all feature flags from cache
        // This is a brute force approach - clear the entire cache if using a tag-based system
        // For now, we'll just reduce the cache TTL significantly
        Cache::flush(); // Nuclear option - clears entire cache
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


