<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class VideoThumbnailHelper
{
    /** FFmpeg binary (config or default). */
    private static function ffmpegPath(): string
    {
        return config('app.ffmpeg_path', 'ffmpeg');
    }

    /** FFprobe binary (config or default). */
    private static function ffprobePath(): string
    {
        return config('app.ffprobe_path', 'ffprobe');
    }

    /**
     * Run a command without using shell (proc_open with array = no exec/shell_exec).
     * @return array{0: string, 1: string, 2: int}|null [stdout, stderr, returnCode] or null if proc_open unavailable/fails
     */
    private static function runProcess(array $command): ?array
    {
        if (!function_exists('proc_open')) {
            return null;
        }
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open($command, $descriptorspec, $pipes, null, null);
        if (!is_resource($process)) {
            return null;
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        return [
            $stdout !== false ? $stdout : '',
            $stderr !== false ? $stderr : '',
            $status,
        ];
    }

    /**
     * Generate a thumbnail from a video file using FFmpeg
     *
     * Uses proc_open (no exec/shell_exec) so it works when those are disabled on hosting.
     *
     * @param string $videoPath Full path to the video file
     * @param string $storageDisk Disk name (e.g., 'public')
     * @param string $outputPath Path where thumbnail should be saved (relative to disk root)
     * @param int $timeOffset Seconds into video to capture thumbnail (default: 1)
     * @param int $width Thumbnail width (default: 320)
     * @param int $height Thumbnail height (default: 240)
     * @return string|null Path to generated thumbnail or null on failure
     */
    public static function generateThumbnail(
        string $videoPath,
        string $storageDisk = 'public',
        string $outputPath = null,
        int $timeOffset = 1,
        int $width = 320,
        int $height = 240
    ): ?string {
        try {
            $fullVideoPath = Storage::disk($storageDisk)->path($videoPath);

            if (!file_exists($fullVideoPath)) {
                Log::error('Video file not found for thumbnail generation', [
                    'video_path' => $fullVideoPath,
                ]);
                return null;
            }

            if (!$outputPath) {
                $pathInfo = pathinfo($videoPath);
                $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_thumb.jpg';
            }

            $fullThumbPath = Storage::disk($storageDisk)->path($outputPath);
            $thumbDir = dirname($fullThumbPath);

            if (!is_dir($thumbDir)) {
                mkdir($thumbDir, 0755, true);
            }

            $vf = sprintf(
                'scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2',
                $width,
                $height,
                $width,
                $height
            );
            $command = [
                self::ffmpegPath(),
                '-ss', (string) $timeOffset,
                '-i', $fullVideoPath,
                '-vframes', '1',
                '-vf', $vf,
                '-q:v', '2',
                '-y',
                $fullThumbPath,
            ];

            $result = self::runProcess($command);
            if ($result === null) {
                Log::warning('Thumbnail generation skipped (proc_open unavailable)');
                return null;
            }
            [$stdout, $stderr, $returnCode] = $result;

            if ($returnCode !== 0 || !file_exists($fullThumbPath)) {
                Log::error('FFmpeg thumbnail generation failed', [
                    'return_code' => $returnCode,
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                    'video_path' => $fullVideoPath,
                ]);
                return null;
            }

            Log::info('Video thumbnail generated successfully', [
                'video_path' => $videoPath,
                'thumbnail_path' => $outputPath,
            ]);

            return $outputPath;
        } catch (\Throwable $e) {
            Log::error('Exception during thumbnail generation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'video_path' => $videoPath,
            ]);
            return null;
        }
    }

    /**
     * Get video duration using FFprobe (part of FFmpeg).
     * Uses proc_open so it works when exec/shell_exec are disabled.
     *
     * @param string $videoPath Full path to the video file
     * @param string $storageDisk Disk name (e.g., 'public')
     * @return float|null Duration in seconds or null on failure
     */
    public static function getVideoDuration(
        string $videoPath,
        string $storageDisk = 'public'
    ): ?float {
        try {
            $fullVideoPath = Storage::disk($storageDisk)->path($videoPath);

            if (!file_exists($fullVideoPath)) {
                return null;
            }

            $command = [
                self::ffprobePath(),
                '-v', 'error',
                '-show_entries', 'format=duration',
                '-of', 'default=noprint_wrappers=1:nokey=1',
                $fullVideoPath,
            ];

            $result = self::runProcess($command);
            if ($result === null) {
                return null;
            }
            [$stdout, $stderr, $returnCode] = $result;

            $duration = trim($stdout);
            if ($returnCode !== 0 || !is_numeric($duration)) {
                Log::warning('Failed to get video duration', [
                    'video_path' => $fullVideoPath,
                    'return_code' => $returnCode,
                    'output' => $stdout . $stderr,
                ]);
                return null;
            }

            return (float) $duration;
        } catch (\Throwable $e) {
            Log::error('Exception getting video duration', [
                'error' => $e->getMessage(),
                'video_path' => $videoPath,
            ]);
            return null;
        }
    }
}
