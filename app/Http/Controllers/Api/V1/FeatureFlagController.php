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

