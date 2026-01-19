<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class VideoThumbnailHelper
{
    /**
     * Generate a thumbnail from a video file using FFmpeg
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
            // Get full path to video file
            $fullVideoPath = Storage::disk($storageDisk)->path($videoPath);
            
            if (!file_exists($fullVideoPath)) {
                Log::error('Video file not found for thumbnail generation', [
                    'video_path' => $fullVideoPath,
                ]);
                return null;
            }

            // Generate output path if not provided
            if (!$outputPath) {
                $pathInfo = pathinfo($videoPath);
                $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_thumb.jpg';
            }

            $fullThumbPath = Storage::disk($storageDisk)->path($outputPath);
            $thumbDir = dirname($fullThumbPath);
            
            // Create directory if it doesn't exist
            if (!is_dir($thumbDir)) {
                mkdir($thumbDir, 0755, true);
            }

            // FFmpeg command to extract frame at specified time
            // -ss: seek to time offset
            // -i: input file
            // -vframes 1: extract only 1 frame
            // -vf scale: resize to specified dimensions
            // -q:v 2: high quality JPEG
            $command = sprintf(
                'ffmpeg -ss %d -i %s -vframes 1 -vf "scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2" -q:v 2 -y %s 2>&1',
                $timeOffset,
                escapeshellarg($fullVideoPath),
                $width,
                $height,
                $width,
                $height,
                escapeshellarg($fullThumbPath)
            );

            // Execute FFmpeg command
            exec($command, $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($fullThumbPath)) {
                Log::error('FFmpeg thumbnail generation failed', [
                    'command' => $command,
                    'return_code' => $returnCode,
                    'output' => implode("\n", $output),
                    'video_path' => $fullVideoPath,
                ]);
                return null;
            }

            Log::info('Video thumbnail generated successfully', [
                'video_path' => $videoPath,
                'thumbnail_path' => $outputPath,
            ]);

            return $outputPath;
        } catch (\Exception $e) {
            Log::error('Exception during thumbnail generation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'video_path' => $videoPath,
            ]);
            return null;
        }
    }

    /**
     * Get video duration using FFprobe (part of FFmpeg)
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

            // Use ffprobe to get duration
            $command = sprintf(
                'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1',
                escapeshellarg($fullVideoPath)
            );

            $duration = exec($command, $output, $returnCode);

            if ($returnCode !== 0 || !is_numeric($duration)) {
                Log::warning('Failed to get video duration', [
                    'video_path' => $fullVideoPath,
                    'return_code' => $returnCode,
                    'output' => implode("\n", $output),
                ]);
                return null;
            }

            return (float) $duration;
        } catch (\Exception $e) {
            Log::error('Exception getting video duration', [
                'error' => $e->getMessage(),
                'video_path' => $videoPath,
            ]);
            return null;
        }
    }
}
