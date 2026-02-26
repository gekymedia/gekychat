<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class EngagementBoostService
{
    /**
     * Cache key for boost settings
     */
    const CACHE_KEY = 'engagement_boost_settings';
    const CACHE_TTL = 300; // 5 minutes

    /**
     * Get all boost settings
     */
    public static function getSettings(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return [
                'enabled' => SystemSetting::getValue('engagement_boost_enabled', false),
                'views_multiplier' => SystemSetting::getValue('engagement_boost_views_multiplier', 1),
                'likes_multiplier' => SystemSetting::getValue('engagement_boost_likes_multiplier', 1),
                'comments_multiplier' => SystemSetting::getValue('engagement_boost_comments_multiplier', 1),
                'shares_multiplier' => SystemSetting::getValue('engagement_boost_shares_multiplier', 1),
            ];
        });
    }

    /**
     * Check if engagement boost is enabled
     */
    public static function isEnabled(): bool
    {
        return self::getSettings()['enabled'];
    }

    /**
     * Apply boost multiplier to views count
     */
    public static function boostViews(int $count): int
    {
        $settings = self::getSettings();
        if (!$settings['enabled']) {
            return $count;
        }
        return (int) round($count * $settings['views_multiplier']);
    }

    /**
     * Apply boost multiplier to likes count
     */
    public static function boostLikes(int $count): int
    {
        $settings = self::getSettings();
        if (!$settings['enabled']) {
            return $count;
        }
        return (int) round($count * $settings['likes_multiplier']);
    }

    /**
     * Apply boost multiplier to comments count
     */
    public static function boostComments(int $count): int
    {
        $settings = self::getSettings();
        if (!$settings['enabled']) {
            return $count;
        }
        return (int) round($count * $settings['comments_multiplier']);
    }

    /**
     * Apply boost multiplier to shares count
     */
    public static function boostShares(int $count): int
    {
        $settings = self::getSettings();
        if (!$settings['enabled']) {
            return $count;
        }
        return (int) round($count * $settings['shares_multiplier']);
    }

    /**
     * Apply all boost multipliers to a post's metrics
     */
    public static function boostPostMetrics(array $post): array
    {
        if (!self::isEnabled()) {
            return $post;
        }

        if (isset($post['views_count'])) {
            $post['views_count'] = self::boostViews($post['views_count']);
        }
        if (isset($post['likes_count'])) {
            $post['likes_count'] = self::boostLikes($post['likes_count']);
        }
        if (isset($post['comments_count'])) {
            $post['comments_count'] = self::boostComments($post['comments_count']);
        }
        if (isset($post['shares_count'])) {
            $post['shares_count'] = self::boostShares($post['shares_count']);
        }

        return $post;
    }

    /**
     * Clear the cached settings
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
