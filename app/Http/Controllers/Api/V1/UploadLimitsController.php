<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\VideoUploadLimitService;
use Illuminate\Http\Request;

/**
 * Upload Limits Controller
 * 
 * Provides endpoints for clients to get upload limits for current user
 */
class UploadLimitsController extends Controller
{
    /**
     * Get upload limits for current user
     * GET /api/v1/upload-limits
     * 
     * Returns effective limits (user override or global defaults)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $limitService = app(VideoUploadLimitService::class);
        
        return response()->json([
            'data' => $limitService->getUserLimits($user->id),
        ]);
    }
}
