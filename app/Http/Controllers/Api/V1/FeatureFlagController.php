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

        // If testing mode is enabled, only allow allowlisted users to receive flags
        if (\App\Services\TestingModeService::isEnabled()
            && !\App\Services\TestingModeService::isUserInTestingMode($user->id)) {
            return response()->json([
                'data' => [],
            ]);
        }

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

