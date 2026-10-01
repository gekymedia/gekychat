<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Audio\AudioService;
use App\Services\Audio\FreesoundClient;
use App\Models\AudioLibrary;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AudioController extends Controller
{
    private AudioService $audioService;
    private FreesoundClient $freesoundClient;
    
    public function __construct(AudioService $audioService, FreesoundClient $freesoundClient)
    {
        $this->audioService = $audioService;
        $this->freesoundClient = $freesoundClient;
    }
    
    /**
     * Search for audio
     * GET /api/v1/audio/search
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
            'page' => 'nullable|integer|min:1',
            'max_duration' => 'nullable|integer|min:1|max:300',
        ]);
        
        $query = $request->input('q');
        $page = $request->input('page', 1);
        $filters = [];
        
        if ($request->has('max_duration')) {
            $filters['max_duration'] = $request->input('max_duration');
        }
        
        try {
            $results = $this->audioService->search($query, $filters, $page);
            
            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get trending audio
     * GET /api/v1/audio/trending
     */
    public function trending(Request $request): JsonResponse
    {
        $days = $request->input('days', 7);
        $limit = $request->input('limit', 20);
        
        $trending = $this->audioService->getTrending($days, $limit);
        
        return response()->json([
            'success' => true,
            'data' => $trending,
        ]);
    }

    /**
     * Browse locally hosted / cached audio library
     * GET /api/v1/audio/library
     */
    public function library(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
            'category' => 'nullable|string|max:100',
            'q' => 'nullable|string|min:1|max:100',
        ]);

        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 50);
        $items = $this->audioService->browseLibrary(
            $limit,
            $page,
            $request->input('category'),
            $request->input('q')
        );

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'count' => $items->count(),
            ],
        ]);
    }
    
    /**
     * Get audio details
     * GET /api/v1/audio/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $audio = $this->audioService->getAudioDetails($id);
            
            return response()->json([
                'success' => true,
                'data' => $audio,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Audio not found',
            ], 404);
        }
    }
    
    /**
     * Get audio preview URL
     * GET /api/v1/audio/{id}/preview
     */
    public function preview(int $id): JsonResponse
    {
        try {
            $audio = AudioLibrary::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'preview_url' => $audio->preview_url,
                    'duration' => $audio->duration,
                    'name' => $audio->name,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Audio not found',
            ], 404);
        }
    }
    
    /**
     * Get similar audio
     * GET /api/v1/audio/{id}/similar
     */
    public function similar(int $id): JsonResponse
    {
        try {
            $audio = AudioLibrary::findOrFail($id);
            
            // Try to get similar from Freesound when this track came from there.
            $similar = [];
            if (!empty($audio->freesound_id)) {
                try {
                    $similar = $this->freesoundClient->getSimilar($audio->freesound_id);
                } catch (\Throwable $e) {
                    $similar = [];
                }
            }
            
            // Also get similar from our database based on category / tags
            $localQuery = AudioLibrary::active()->where('id', '!=', $id);
            if ($audio->category) {
                $localQuery->where('category', $audio->category);
            }
            $localSimilar = $localQuery->orderByDesc('usage_count')->limit(8)->get();
            if ($localSimilar->isEmpty()) {
                $localSimilar = AudioLibrary::active()
                    ->where('id', '!=', $id)
                    ->orderByDesc('usage_count')
                    ->limit(8)
                    ->get();
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'freesound' => $similar,
                    'local' => $localSimilar,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get similar audio',
            ], 500);
        }
    }
    
    /**
     * Validate audio for use
     * POST /api/v1/audio/{id}/validate
     */
    public function validateAudio(int $id): JsonResponse
    {
        try {
            $audio = $this->audioService->validateAudioForUse($id);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'valid' => true,
                    'audio' => $audio,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'valid' => false,
                ],
            ], 400);
        }
    }
    
    /**
     * Get popular categories
     * GET /api/v1/audio/categories
     */
    public function categories(): JsonResponse
    {
        $categories = $this->audioService->getPopularCategories();
        
        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
    
    /**
     * Get popular tags
     * GET /api/v1/audio/tags
     */
    public function tags(): JsonResponse
    {
        $tags = $this->audioService->getPopularTags();
        
        return response()->json([
            'success' => true,
            'data' => $tags,
        ]);
    }
}
