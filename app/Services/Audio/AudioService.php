<?php

namespace App\Services\Audio;

use App\Models\AudioLibrary;
use App\Models\AudioUsageStats;
use App\Models\AudioLicenseSnapshot;
use App\Models\WorldFeedPost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AudioService
{
    private FreesoundClient $freesoundClient;
    private LicenseValidator $licenseValidator;
    
    public function __construct(
        FreesoundClient $freesoundClient,
        LicenseValidator $licenseValidator
    ) {
        $this->freesoundClient = $freesoundClient;
        $this->licenseValidator = $licenseValidator;
    }
    
    /**
     * Search for audio
     */
    public function search(string $query, array $filters = [], int $page = 1): array
    {
        // First, check local cache for quick results
        $localResults = AudioLibrary::active()
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhereRaw("JSON_SEARCH(tags, 'one', ?) IS NOT NULL", ["%{$query}%"]);
            })
            ->orderBy('usage_count', 'desc')
            ->limit(10)
            ->get();
        
        // Then fetch from Freesound
        try {
            $freesoundResults = $this->freesoundClient->search($query, $filters, $page);
            
            // Filter for safe licenses
            $safeResults = $this->licenseValidator->filterSafeResults(
                $freesoundResults['results'] ?? []
            );
            
            // Cache new results
            foreach ($safeResults as $sound) {
                try {
                    $this->cacheSound($sound);
                } catch (\Exception $e) {
                    Log::warning('Failed to cache sound', [
                        'sound_id' => $sound['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            return [
                'cached' => $localResults,
                'freesound' => $safeResults,
                'total' => count($localResults) + count($safeResults),
                'page' => $page,
                'has_more' => !empty($freesoundResults['next']),
            ];
        } catch (\Exception $e) {
            Log::error('Audio search failed', ['error' => $e->getMessage()]);
            
            // Return only cached results if Freesound fails
            return [
                'cached' => $localResults,
                'freesound' => [],
                'total' => count($localResults),
                'page' => $page,
                'has_more' => false,
                'error' => 'External search temporarily unavailable',
            ];
        }
    }
    
    /**
     * Cache a sound from Freesound
     */
    public function cacheSound(array $soundData): AudioLibrary
    {
        $licenseInfo = $this->licenseValidator->validateAndPrepare($soundData);
        
        return AudioLibrary::updateOrCreate(
            ['freesound_id' => $soundData['id']],
            [
                'freesound_username' => $soundData['username'] ?? null,
                'name' => $soundData['name'],
                'description' => $soundData['description'] ?? null,
                'duration' => $soundData['duration'],
                'file_size' => $soundData['filesize'] ?? null,
                'preview_url' => $soundData['previews']['preview-hq-mp3'] ?? $soundData['previews']['preview-lq-mp3'] ?? '',
                'download_url' => $soundData['download'] ?? null,
                'license_type' => $licenseInfo['license_type'],
                'license_url' => $soundData['license'] ?? '',
                'license_snapshot' => $soundData,
                'attribution_required' => $licenseInfo['attribution_required'],
                'attribution_text' => $licenseInfo['attribution_text'],
                'tags' => $soundData['tags'] ?? [],
                'cached_at' => now(),
                'cache_expires_at' => now()->addDays(30),
                'validation_status' => 'approved',
                'is_active' => true,
            ]
        );
    }
    
    /**
     * Validate audio for use
     */
    public function validateAudioForUse(int $audioId): AudioLibrary
    {
        $audio = AudioLibrary::findOrFail($audioId);
        
        if (!$audio->is_active) {
            throw new \Exception('This audio is no longer available');
        }
        
        if ($audio->validation_status !== 'approved') {
            throw new \Exception('This audio has not been approved for use');
        }
        
        // Re-validate license
        if (!$this->licenseValidator->isSafeLicense($audio->license_type)) {
            $audio->update(['is_active' => false]);
            throw new \Exception('Audio license is no longer valid');
        }
        
        return $audio;
    }
    
    /**
     * Attach audio to a world feed post
     */
    public function attachToPost(int $postId, int $audioId, int $userId, array $settings = []): void
    {
        $audio = $this->validateAudioForUse($audioId);
        
        DB::beginTransaction();
        try {
            // Create or update association
            DB::table('world_feed_audio')->updateOrInsert(
                ['world_feed_post_id' => $postId],
                [
                    'audio_library_id' => $audioId,
                    'volume_level' => $settings['volume'] ?? 100,
                    'audio_start_time' => $settings['start_time'] ?? 0,
                    'loop_audio' => $settings['loop'] ?? true,
                    'fade_in_duration' => $settings['fade_in'] ?? 0,
                    'fade_out_duration' => $settings['fade_out'] ?? 0,
                    'license_snapshot' => json_encode($audio->license_snapshot),
                    'attribution_displayed' => $audio->attribution_text,
                    'attached_by' => $userId,
                    'attached_at' => now(),
                ]
            );
            
            // Create license snapshot for compliance
            AudioLicenseSnapshot::create([
                'audio_library_id' => $audioId,
                'world_feed_post_id' => $postId,
                'license_type' => $audio->license_type,
                'license_url' => $audio->license_url,
                'freesound_metadata' => $audio->license_snapshot,
                'validated_at' => now(),
                'is_compliant' => true,
            ]);
            
            // Update usage stats
            $audio->increment('usage_count');
            $audio->update(['last_used_at' => now()]);
            
            // Record usage in stats table
            $this->recordUsage($audioId, $userId);
            
            // Update post
            WorldFeedPost::where('id', $postId)->update([
                'has_audio' => true,
                'audio_attribution' => $audio->attribution_text,
            ]);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to attach audio to post', [
                'post_id' => $postId,
                'audio_id' => $audioId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Record audio usage in stats
     */
    private function recordUsage(int $audioId, int $userId): void
    {
        $date = now()->toDateString();
        $hour = now()->hour;
        
        // Update hourly stats
        AudioUsageStats::updateOrCreate(
            [
                'audio_library_id' => $audioId,
                'date' => $date,
                'hour' => $hour,
            ],
            [
                'usage_count' => DB::raw('usage_count + 1'),
            ]
        );
        
        // Update daily aggregate (hour = null)
        AudioUsageStats::updateOrCreate(
            [
                'audio_library_id' => $audioId,
                'date' => $date,
                'hour' => null,
            ],
            [
                'usage_count' => DB::raw('usage_count + 1'),
            ]
        );
    }
    
    /**
     * Get trending audio
     */
    public function getTrending(int $days = 7, int $limit = 20): \Illuminate\Support\Collection
    {
        return AudioLibrary::trending($days)->limit($limit)->get();
    }
    
    /**
     * Get audio by ID with full details
     */
    public function getAudioDetails(int $audioId): AudioLibrary
    {
        return AudioLibrary::with(['usageStats', 'licenseSnapshots'])
            ->findOrFail($audioId);
    }
    
    /**
     * Remove audio from post
     */
    public function removeFromPost(int $postId): void
    {
        DB::beginTransaction();
        try {
            // Delete association
            DB::table('world_feed_audio')
                ->where('world_feed_post_id', $postId)
                ->delete();
            
            // Update post
            WorldFeedPost::where('id', $postId)->update([
                'has_audio' => false,
                'audio_attribution' => null,
            ]);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Get popular categories
     */
    public function getPopularCategories(int $limit = 10): array
    {
        return AudioLibrary::active()
            ->whereNotNull('category')
            ->select('category', DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->pluck('count', 'category')
            ->toArray();
    }
    
    /**
     * Get popular tags
     */
    public function getPopularTags(int $limit = 20): array
    {
        $audio = AudioLibrary::active()
            ->whereNotNull('tags')
            ->get();
        
        $tagCounts = [];
        foreach ($audio as $item) {
            foreach ($item->tags ?? [] as $tag) {
                $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
            }
        }
        
        arsort($tagCounts);
        return array_slice($tagCounts, 0, $limit, true);
    }
}
