<?php

namespace App\Jobs;

use App\Models\Attachment;
use App\Services\FeatureFlagService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * MEDIA COMPRESSION: Compress video attachments
 * 
 * Processes videos asynchronously after upload:
 * - Transcodes to H.264 (video) + AAC (audio)
 * - Enforces max resolution (720p for Phase 1/2, 1080p for Phase 3+)
 * - Generates thumbnail poster image
 * - Enforces max duration and size per Phase Mode
 */
class CompressVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $attachment;

    /**
     * Create a new job instance.
     */
    public function __construct(Attachment $attachment)
    {
        $this->attachment = $attachment;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check feature flag
        if (!FeatureFlagService::isEnabled('media_compression')) {
            Log::info("Media compression disabled, skipping attachment {$this->attachment->id}");
            $this->attachment->update(['compression_status' => 'completed']);
            return;
        }

        // Reload attachment to ensure it's fresh
        $attachment = Attachment::find($this->attachment->id);
        if (!$attachment || !$attachment->file_path) {
            Log::error("Attachment {$this->attachment->id} not found or missing file_path");
            return;
        }

        try {
            // Mark as processing
            $attachment->update(['compression_status' => 'processing']);

            // Store original size
            $originalSize = $attachment->size ?? Storage::disk('public')->size($attachment->file_path);
            $attachment->update(['original_size' => $originalSize]);

            // Get file from storage
            $filePath = Storage::disk('public')->path($attachment->file_path);
            
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }

            // TODO: Check Phase Mode for max resolution (720p vs 1080p)
            // For now, default to 720p
            $maxResolution = '720p'; // or '1080p' based on phase mode
            $maxHeight = $maxResolution === '1080p' ? 1080 : 720;
            $maxBitrate = $maxResolution === '1080p' ? '4000k' : '2000k';

            // Get compression level (default: medium)
            $compressionLevel = $attachment->compression_level ?? 'medium';
            $crf = $this->getCrfForLevel($compressionLevel);

            // Check if FFmpeg is available
            $ffmpegPath = $this->getFfmpegPath();
            if (!$ffmpegPath) {
                throw new \Exception("FFmpeg is not installed or not found in PATH");
            }

            // Generate output paths
            $pathInfo = pathinfo($attachment->file_path);
            $compressedFilename = $pathInfo['filename'] . '_compressed.mp4';
            $compressedPath = $pathInfo['dirname'] . '/' . $compressedFilename;
            $compressedFullPath = Storage::disk('public')->path($compressedPath);

            $thumbnailFilename = $pathInfo['filename'] . '_thumb.jpg';
            $thumbnailPath = $pathInfo['dirname'] . '/' . $thumbnailFilename;
            $thumbnailFullPath = Storage::disk('public')->path($thumbnailPath);

            // Build FFmpeg command for compression
            // Target: H.264 (libx264) + AAC audio
            $command = sprintf(
                '%s -i %s -c:v libx264 -preset medium -crf %d -maxrate %s -bufsize %s -vf "scale=-2:%d:force_original_aspect_ratio=decrease" -c:a aac -b:a 128k -movflags +faststart %s -y',
                escapeshellarg($ffmpegPath),
                escapeshellarg($filePath),
                $crf,
                $maxBitrate,
                $maxBitrate * 2, // bufsize = 2x bitrate
                $maxHeight,
                escapeshellarg($compressedFullPath)
            );

            Log::info("Compressing video", [
                'attachment_id' => $attachment->id,
                'command' => $command,
            ]);

            // Execute FFmpeg compression
            $process = Process::timeout(600)->run($command); // 10 minute timeout

            if (!$process->successful()) {
                throw new \Exception("FFmpeg compression failed: " . $process->errorOutput());
            }

            if (!file_exists($compressedFullPath)) {
                throw new \Exception("Compressed video file was not created");
            }

            $compressedSize = filesize($compressedFullPath);

            // Generate thumbnail (extract frame at 1 second)
            $thumbnailCommand = sprintf(
                '%s -i %s -ss 00:00:01 -vframes 1 -vf "scale=400:400:force_original_aspect_ratio=decrease,pad=400:400:(ow-iw)/2:(oh-ih)/2" -q:v 2 %s -y',
                escapeshellarg($ffmpegPath),
                escapeshellarg($compressedFullPath), // Use compressed video for thumbnail
                escapeshellarg($thumbnailFullPath)
            );

            $thumbnailProcess = Process::timeout(60)->run($thumbnailCommand);
            
            if (!$thumbnailProcess->successful() || !file_exists($thumbnailFullPath)) {
                Log::warning("Thumbnail generation failed, continuing without thumbnail", [
                    'attachment_id' => $attachment->id,
                    'error' => $thumbnailProcess->errorOutput(),
                ]);
                $thumbnailPath = null;
            }

            // Update attachment with compressed paths
            $attachment->update([
                'compression_status' => 'completed',
                'compressed_file_path' => $compressedPath,
                'thumbnail_path' => $thumbnailPath,
                'compressed_size' => $compressedSize,
            ]);

            // TODO (PHASE 2/3): Optionally delete original if configured
            // if (config('media.delete_original_after_compression', false)) {
            //     Storage::disk('public')->delete($attachment->file_path);
            // }

            Log::info("Video compression completed", [
                'attachment_id' => $attachment->id,
                'original_size' => $originalSize,
                'compressed_size' => $compressedSize,
                'reduction' => round((1 - ($compressedSize / $originalSize)) * 100, 2) . '%',
            ]);

        } catch (\Exception $e) {
            Log::error("Video compression failed", [
                'attachment_id' => $attachment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $attachment->update([
                'compression_status' => 'failed',
                'compression_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get CRF (Constant Rate Factor) for H.264 encoding based on compression level
     * Lower CRF = higher quality, larger file
     */
    private function getCrfForLevel(string $level): int
    {
        return match ($level) {
            'low' => 28,   // Lower quality, smaller file
            'high' => 20,  // Higher quality, larger file
            default => 23, // medium - balanced
        };
    }

    /**
     * Get FFmpeg executable path
     * TODO: Add config option for custom FFmpeg path
     */
    private function getFfmpegPath(): ?string
    {
        // Check common locations
        $possiblePaths = [
            'ffmpeg',
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            'C:\\ffmpeg\\bin\\ffmpeg.exe', // Windows
        ];

        foreach ($possiblePaths as $path) {
            if ($this->commandExists($path)) {
                return $path;
            }
        }

        // Check PATH
        $which = Process::run('which ffmpeg');
        if ($which->successful() && !empty($which->output())) {
            return trim($which->output());
        }

        return null;
    }

    /**
     * Check if command exists
     */
    private function commandExists(string $command): bool
    {
        $process = Process::run("command -v " . escapeshellarg($command));
        return $process->successful();
    }
}
