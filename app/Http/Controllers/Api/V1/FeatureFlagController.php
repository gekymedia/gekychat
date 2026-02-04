<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FeatureFlagService;
use Illuminate\Http\Request;

/**
 * PHASE 2: Feature Flag Controller
 * 
 * Returns enabled feature flags for the authenticated user.
 */
class FeatureFlagController extends Controller
{
    /**
     * Get all enabled feature flags for the current user/platform
     * GET /api/v1/feature-flags
     * 
     * Note: This endpoint works with or without authentication.
     * If not authenticated, returns empty array (all features disabled).
     */
    public function index(Request $request)
    {
        $platform = $request->input('platform', 'mobile'); // 'web', 'mobile', 'desktop'
        $user = $request->user();

        // If not authenticated, return empty feature list
        if (!$user) {
            return response()->json([
                'data' => [],
            ]);
        }

        // If testing mode is enabled AND user is in testing mode, enable ALL flagged features
        if (\App\Services\TestingModeService::isEnabled()
            && \App\Services\TestingModeService::isUserInTestingMode($user->id)) {
            // User is in testing mode - enable ALL feature flags
            $allFlags = \App\Models\FeatureFlag::where('enabled', true)
                ->where(function ($q) use ($platform) {
                    $q->where('platform', $platform)
                      ->orWhere('platform', 'all');
                })
                ->pluck('key')
                ->toArray();
                
            $result = [];
            foreach ($allFlags as $flag) {
                $result[] = [
                    'key' => $flag,
                    'enabled' => true,
                ];
            }
            
            return response()->json([
                'data' => $result,
            ]);
        }

        // Regular users: get enabled flags based on platform
        $flags = FeatureFlagService::getEnabled($platform);
        
        // Convert array to format expected by frontend
        $result = [];
        foreach ($flags as $flag) {
            $result[] = [
                'key' => $flag,
                'enabled' => true,
            ];
        }
        
        return response()->json([
            'data' => $result,
        ]);
    }
}

