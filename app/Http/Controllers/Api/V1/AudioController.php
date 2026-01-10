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
            
            // Try to get similar from Freesound
            $similar = $this->freesoundClient->getSimilar($audio->freesound_id);
            
            // Also get similar from our database based on tags
            $localSimilar = AudioLibrary::active()
                ->where('id', '!=', $id)
                ->whereNotNull('tags')
                ->get()
                ->filter(function($item) use ($audio) {
                    $commonTags = array_intersect($item->tags ?? [], $audio->tags ?? []);
                    return count($commonTags) > 0;
                })
                ->take(5);
            
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
    public function validate(int $id): JsonResponse
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
