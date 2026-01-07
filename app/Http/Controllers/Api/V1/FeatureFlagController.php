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
     */
    public function index(Request $request)
    {
        $platform = $request->input('platform', 'mobile'); // 'web', 'mobile', 'desktop'
        
        $flags = FeatureFlagService::getEnabled($platform);
        
        // Convert array to associative array with boolean values
        $result = [];
        foreach ($flags as $flag) {
            $result[$flag] = true;
        }
        
        return response()->json($result);
    }
}

