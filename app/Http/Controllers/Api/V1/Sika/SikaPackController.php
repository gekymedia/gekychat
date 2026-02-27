<?php

namespace App\Http\Controllers\Api\V1\Sika;

use App\Http\Controllers\Controller;
use App\Http\Resources\Sika\SikaPackResource;
use App\Models\Sika\SikaPack;
use Illuminate\Http\JsonResponse;

class SikaPackController extends Controller
{
    /**
     * List all active coin packs
     * 
     * GET /api/sika/packs
     */
    public function index(): JsonResponse
    {
        $packs = SikaPack::active()
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => SikaPackResource::collection($packs),
        ]);
    }

    /**
     * Get a specific pack
     * 
     * GET /api/sika/packs/{id}
     */
    public function show(int $id): JsonResponse
    {
        $pack = SikaPack::active()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new SikaPackResource($pack),
        ]);
    }
}
