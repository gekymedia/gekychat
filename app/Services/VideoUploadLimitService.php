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
    /** World Feed hard cap (bytes). 3 min of 720p after client compress is often 40–120 MB. */
    public const WORLD_FEED_MAX_SIZE = 200 * 1024 * 1024;

    /**
     * Status video hard cap (bytes).
     * iPhone camera clips are often 6–10 MB/s; 9s can be ~70 MB before
     * client compression. 100 MB matches World Feed so a compressed (or
     * worst-case raw) clip can still land.
     */
    public const STATUS_MAX_SIZE = 100 * 1024 * 1024;

    /**
     * Keep expired status rows/files this long after expires_at (24h visibility
     * + grace for clock skew, chat replies, and delayed viewers).
     */
    public const STATUS_PURGE_HOURS_AFTER_EXPIRY = 48;

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
        
        $maxSize = self::WORLD_FEED_MAX_SIZE;
        if ($file->getSize() > $maxSize) {
            $maxSizeMB = (int) round($maxSize / (1024 * 1024));
            return [
                'valid' => false,
                'error' => "This video exceeds your upload size limit ({$maxSizeMB} MB).",
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
        
        $maxSize = self::STATUS_MAX_SIZE;
        if ($file->getSize() > $maxSize) {
            $maxSizeMB = (int) round($maxSize / (1024 * 1024));
            return [
                'valid' => false,
                'error' => "This video exceeds your upload size limit ({$maxSizeMB} MB).",
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
     * Uses FFprobe via proc_open (no shell_exec) so it works when shell_exec is disabled on hosting.
     * If duration cannot be determined, returns null and callers allow the upload.
     */
    protected function getVideoDuration(UploadedFile $file): ?int
    {
        $ffprobePath = config('app.ffprobe_path', 'ffprobe');

        try {
            $path = $file->getRealPath();

            if (!$path || !file_exists($path)) {
                return null;
            }

            // Run ffprobe without using shell (array form = no shell, works when shell_exec is disabled)
            $command = [
                $ffprobePath,
                '-v', 'error',
                '-show_entries', 'format=duration',
                '-of', 'default=noprint_wrappers=1:nokey=1',
                $path,
            ];

            $output = $this->runProcess($command);

            if ($output === null || trim($output) === '') {
                return null;
            }

            $duration = (float) trim($output);

            return $duration > 0 ? (int) ceil($duration) : null;
        } catch (\Throwable $e) {
            \Log::warning('Failed to extract video duration', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Run a command and return stdout (no shell - works when shell_exec/exec are disabled).
     */
    private function runProcess(array $command): ?string
    {
        if (!function_exists('proc_open')) {
            return null;
        }

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open(
            $command,
            $descriptorspec,
            $pipes,
            null,
            null
        );

        if (!is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $stdout !== false ? $stdout : null;
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
                'max_size' => self::WORLD_FEED_MAX_SIZE,
            ],
            'status' => [
                'max_duration' => $this->getEffectiveLimit($userId, 'status_max_duration'),
                'max_size' => self::STATUS_MAX_SIZE,
            ],
            'chat' => [
                'max_size' => $this->getEffectiveLimit($userId, 'chat_video_max_size'),
                // No duration limit for chat
            ],
        ];
    }
}
