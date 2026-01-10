<?php

namespace App\Services\Audio;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FreesoundClient
{
    private string $apiKey;
    private string $baseUrl = 'https://freesound.org/apiv2';
    
    public function __construct()
    {
        $this->apiKey = config('services.freesound.api_key', '');
        
        if (empty($this->apiKey)) {
            Log::warning('Freesound API key not configured');
        }
    }
    
    /**
     * Search for sounds
     */
    public function search(string $query, array $filters = [], int $page = 1, int $pageSize = 20): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Freesound API key not configured');
        }
        
        $params = [
            'query' => $query,
            'page' => $page,
            'page_size' => $pageSize,
            'fields' => 'id,name,description,duration,username,license,previews,download,tags,filesize',
            'token' => $this->apiKey,
        ];
        
        // Filter by duration (e.g., 5-120 seconds for short videos)
        if (isset($filters['max_duration'])) {
            $params['filter'] = "duration:[0 TO {$filters['max_duration']}]";
        }
        
        // Filter by license
        if (isset($filters['license'])) {
            $params['filter'] = ($params['filter'] ?? '') . " license:\"{$filters['license']}\"";
        }
        
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/search/text/", $params);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            Log::error('Freesound API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            
            throw new \Exception('Freesound API error: ' . $response->status());
        } catch (\Exception $e) {
            Log::error('Freesound search failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Get detailed information about a sound
     */
    public function getSound(int $soundId): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Freesound API key not configured');
        }
        
        $cacheKey = "freesound_sound_{$soundId}";
        
        return Cache::remember($cacheKey, 3600, function() use ($soundId) {
            try {
                $response = Http::timeout(10)->get("{$this->baseUrl}/sounds/{$soundId}/", [
                    'token' => $this->apiKey,
                ]);
                
                if ($response->successful()) {
                    return $response->json();
                }
                
                throw new \Exception('Sound not found');
            } catch (\Exception $e) {
                Log::error('Freesound getSound failed', [
                    'sound_id' => $soundId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }
    
    /**
     * Download sound preview
     */
    public function downloadPreview(int $soundId, string $savePath): bool
    {
        try {
            $sound = $this->getSound($soundId);
            
            // Use high-quality preview (no OAuth required)
            $previewUrl = $sound['previews']['preview-hq-mp3'] ?? $sound['previews']['preview-lq-mp3'] ?? null;
            
            if (!$previewUrl) {
                throw new \Exception('No preview URL available');
            }
            
            $response = Http::timeout(30)->get($previewUrl);
            
            if ($response->successful()) {
                file_put_contents($savePath, $response->body());
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Freesound download failed', [
                'sound_id' => $soundId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Get similar sounds
     */
    public function getSimilar(int $soundId, int $limit = 10): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Freesound API key not configured');
        }
        
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/sounds/{$soundId}/similar/", [
                'token' => $this->apiKey,
                'page_size' => $limit,
                'fields' => 'id,name,description,duration,username,license,previews',
            ]);
            
            if ($response->successful()) {
                return $response->json()['results'] ?? [];
            }
            
            return [];
        } catch (\Exception $e) {
            Log::error('Freesound getSimilar failed', [
                'sound_id' => $soundId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
