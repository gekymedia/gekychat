<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\EngagementBoostService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EngagementBoostController extends Controller
{
    /**
     * Get current engagement boost settings
     */
    public function index(): JsonResponse
    {
        $settings = SystemSetting::getByGroup('engagement_boost');
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'enabled' => $settings['engagement_boost_enabled']['value'] ?? false,
                'multipliers' => [
                    'views' => $settings['engagement_boost_views_multiplier']['value'] ?? 1,
                    'likes' => $settings['engagement_boost_likes_multiplier']['value'] ?? 1,
                    'comments' => $settings['engagement_boost_comments_multiplier']['value'] ?? 1,
                    'shares' => $settings['engagement_boost_shares_multiplier']['value'] ?? 1,
                ],
            ],
        ]);
    }

    /**
     * Toggle engagement boost on/off
     */
    public function toggle(): JsonResponse
    {
        $currentValue = SystemSetting::getValue('engagement_boost_enabled', false);
        $newValue = !$currentValue;
        
        SystemSetting::setValue('engagement_boost_enabled', $newValue ? 'true' : 'false', 'boolean', 'engagement_boost');
        
        // Clear the service cache
        EngagementBoostService::clearCache();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Engagement boost ' . ($newValue ? 'enabled' : 'disabled'),
            'enabled' => $newValue,
        ]);
    }

    /**
     * Update engagement boost multipliers
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'views_multiplier' => 'nullable|numeric|min:1|max:100',
            'likes_multiplier' => 'nullable|numeric|min:1|max:100',
            'comments_multiplier' => 'nullable|numeric|min:1|max:100',
            'shares_multiplier' => 'nullable|numeric|min:1|max:100',
        ]);

        $updated = [];

        if ($request->has('views_multiplier')) {
            SystemSetting::setValue('engagement_boost_views_multiplier', $request->views_multiplier, 'float', 'engagement_boost');
            $updated['views'] = (float) $request->views_multiplier;
        }

        if ($request->has('likes_multiplier')) {
            SystemSetting::setValue('engagement_boost_likes_multiplier', $request->likes_multiplier, 'float', 'engagement_boost');
            $updated['likes'] = (float) $request->likes_multiplier;
        }

        if ($request->has('comments_multiplier')) {
            SystemSetting::setValue('engagement_boost_comments_multiplier', $request->comments_multiplier, 'float', 'engagement_boost');
            $updated['comments'] = (float) $request->comments_multiplier;
        }

        if ($request->has('shares_multiplier')) {
            SystemSetting::setValue('engagement_boost_shares_multiplier', $request->shares_multiplier, 'float', 'engagement_boost');
            $updated['shares'] = (float) $request->shares_multiplier;
        }

        // Clear the service cache
        EngagementBoostService::clearCache();

        return response()->json([
            'status' => 'success',
            'message' => 'Engagement boost multipliers updated successfully',
            'updated' => $updated,
        ]);
    }

    /**
     * Reset all multipliers to default (1x)
     */
    public function reset(): JsonResponse
    {
        SystemSetting::setValue('engagement_boost_enabled', 'false', 'boolean', 'engagement_boost');
        SystemSetting::setValue('engagement_boost_views_multiplier', '1', 'float', 'engagement_boost');
        SystemSetting::setValue('engagement_boost_likes_multiplier', '1', 'float', 'engagement_boost');
        SystemSetting::setValue('engagement_boost_comments_multiplier', '1', 'float', 'engagement_boost');
        SystemSetting::setValue('engagement_boost_shares_multiplier', '1', 'float', 'engagement_boost');

        // Clear the service cache
        EngagementBoostService::clearCache();

        return response()->json([
            'status' => 'success',
            'message' => 'Engagement boost settings reset to defaults (disabled, 1x multipliers)',
        ]);
    }
}
