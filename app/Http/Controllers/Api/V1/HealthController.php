<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    /**
     * Health check endpoint for connectivity detection
     * GET /api/v1/health
     */
    public function index(Request $request)
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ], 200);
    }
}
