<?php

namespace App\Services;

use App\Models\UploadSetting;
use App\Models\UserUploadLimit;
use Illuminate\Http\UploadedFile;

/**
 * Video Upload Limit Service
 * 
 * Handles validation of video uploads with priority:
 * 1. User-specific override (if exists)
 * 2. Global admin settings (default)
 * 
 * Enforces limits on backend (never trust frontend)
 */
class VideoUploadLimitService
{
    /**
     * Get effective limit for a user and upload type
     * 
     * Priority: User override > Global setting
     */
    public function getEffectiveLimit(int $userId, string $limitType): int
    {
        // Check for user override first
        $userOverride = UserUploadLimit::getUserLimit($userId, $limitType);
        
        if ($userOverride !== null) {
            return $userOverride;
        }

        // Fall back to global setting
        return match($limitType) {
            'world_feed_max_duration' => UploadSetting::getWorldFeedMaxDuration(),
            'chat_video_max_size' => UploadSetting::getChatVideoMaxSize(),
            'status_max_duration' => UploadSetting::getStatusMaxDuration(),
            default => throw new \InvalidArgumentException("Unknown limit type: {$limitType}"),
        };
    }

    /**
     * Validate World Feed video upload
     * 
     * @return array ['valid' => bool, 'error' => string|null, 'requires_trim' => bool, 'duration' => int|null]
     */
    public function validateWorldFeedVideo(UploadedFile $file, int $userId): array
    {
        $maxDuration = $this->getEffectiveLimit($userId, 'world_feed_max_duration');
        
        // Check file size (use existing max: 100MB for World Feed)
        $maxSize = 100 * 1024 * 1024; // 100 MB
        if ($file->getSize() > $maxSize) {
            return [
                'valid' => false,
                'error' => 'This video exceeds your upload size limit.',
                'requires_trim' => false,
                'duration' => null,
            ];
        }

        // Extract video duration
        $duration = $this->getVideoDuration($file);
        
        if ($duration === null) {
            // If we can't get duration, allow upload but warn (client should handle)
            return [
                'valid' => true,
                'error' => null,
                'requires_trim' => false,
                'duration' => null,
            ];
        }

        // Check duration limit
        if ($duration > $maxDuration) {
            return [
                'valid' => false,
                'error' => "Please trim your video to the allowed duration ({$maxDuration} seconds).",
                'requires_trim' => true,
                'duration' => $duration,
                'max_duration' => $maxDuration,
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'requires_trim' => false,
            'duration' => $duration,
            'max_duration' => $maxDuration,
        ];
    }

    /**
     * Validate Status video upload
     * 
     * @return array ['valid' => bool, 'error' => string|null, 'requires_trim' => bool, 'duration' => int|null]
     */
    public function validateStatusVideo(UploadedFile $file, int $userId): array
    {
        $maxDuration = $this->getEffectiveLimit($userId, 'status_max_duration');
        
        // Status videos: Max 50MB (existing limit)
        $maxSize = 50 * 1024 * 1024; // 50 MB
        if ($file->getSize() > $maxSize) {
            return [
                'valid' => false,
                'error' => 'This video exceeds your upload size limit.',
                'requires_trim' => false,
                'duration' => null,
            ];
        }

        // Extract video duration
        $duration = $this->getVideoDuration($file);
        
        if ($duration === null) {
            return [
                'valid' => true,
                'error' => null,
                'requires_trim' => false,
                'duration' => null,
            ];
        }

        // Check duration limit
        if ($duration > $maxDuration) {
            return [
                'valid' => false,
                'error' => "Please trim your video to the allowed duration ({$maxDuration} seconds).",
                'requires_trim' => true,
                'duration' => $duration,
                'max_duration' => $maxDuration,
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'requires_trim' => false,
            'duration' => $duration,
            'max_duration' => $maxDuration,
        ];
    }

    /**
     * Validate Chat video upload
     * 
     * Note: Chat videos have NO duration limit, only size limit
     * 
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validateChatVideo(UploadedFile $file, int $userId): array
    {
        $maxSize = $this->getEffectiveLimit($userId, 'chat_video_max_size');
        
        if ($file->getSize() > $maxSize) {
            $maxSizeMB = round($maxSize / (1024 * 1024), 1);
            return [
                'valid' => false,
                'error' => "This video exceeds your upload size limit ({$maxSizeMB} MB).",
            ];
        }

        return [
            'valid' => true,
            'error' => null,
        ];
    }

    /**
     * Get video duration in seconds
     * 
     * Uses FFprobe if available, otherwise returns null
     */
    protected function getVideoDuration(UploadedFile $file): ?int
    {
        // Check if FFprobe is available
        $ffprobePath = config('app.ffprobe_path', 'ffprobe');
        
        try {
            $path = $file->getRealPath();
            
            if (!$path || !file_exists($path)) {
                return null;
            }

            // Use FFprobe to get duration
            $command = sprintf(
                '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "%s"',
                escapeshellarg($ffprobePath),
                escapeshellarg($path)
            );

            $output = \shell_exec($command);
            
            if ($output === null || trim($output) === '') {
                return null;
            }

            $duration = (float) trim($output);
            
            return $duration > 0 ? (int) ceil($duration) : null;
        } catch (\Exception $e) {
            \Log::warning('Failed to extract video duration', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get all limits for a user (for frontend)
     * 
     * Returns limits that frontend can use to adjust UI
     */
    public function getUserLimits(int $userId): array
    {
        return [
            'world_feed' => [
                'max_duration' => $this->getEffectiveLimit($userId, 'world_feed_max_duration'),
                'max_size' => 100 * 1024 * 1024, // 100 MB (hard limit)
            ],
            'status' => [
                'max_duration' => $this->getEffectiveLimit($userId, 'status_max_duration'),
                'max_size' => 50 * 1024 * 1024, // 50 MB (hard limit)
            ],
            'chat' => [
                'max_size' => $this->getEffectiveLimit($userId, 'chat_video_max_size'),
                // No duration limit for chat
            ],
        ];
    }
}
