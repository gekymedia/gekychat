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
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

/**
 * MEDIA COMPRESSION: Compress image attachments
 * 
 * Processes images asynchronously after upload:
 * - Converts to JPEG/WebP
 * - Strips EXIF metadata
 * - Generates thumbnail
 * - Respects user's compression level preference
 */
class CompressImage implements ShouldQueue
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
            // Only compress image files - skip documents, videos, audio, etc.
            $mimeType = $attachment->mime_type ?? '';
            if (!str_starts_with($mimeType, 'image/')) {
                Log::info("Skipping compression for non-image file", [
                    'attachment_id' => $attachment->id,
                    'mime_type' => $mimeType,
                ]);
                $attachment->update(['compression_status' => 'completed']);
                return;
            }

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

            // Get compression level (default: medium)
            $compressionLevel = $attachment->compression_level ?? 'medium';
            $quality = $this->getQualityForLevel($compressionLevel);

            // Load image using Intervention Image
            $manager = new ImageManager(new Driver());
            $image = $manager->read($filePath);

            // Strip EXIF metadata
            $image->strip();

            // Determine max dimensions based on compression level
            $maxDimensions = $this->getMaxDimensions($compressionLevel);
            
            // Resize if necessary (maintain aspect ratio)
            $width = $image->width();
            $height = $image->height();
            
            if ($width > $maxDimensions['width'] || $height > $maxDimensions['height']) {
                $image->scaleDown($maxDimensions['width'], $maxDimensions['height']);
            }

            // Generate compressed version filename
            $pathInfo = pathinfo($attachment->file_path);
            $compressedFilename = $pathInfo['filename'] . '_compressed.jpg';
            $compressedPath = $pathInfo['dirname'] . '/' . $compressedFilename;

            // Save compressed image (always as JPEG for compatibility)
            $compressedData = $image->toJpeg($quality);
            Storage::disk('public')->put($compressedPath, $compressedData);
            
            $compressedSize = Storage::disk('public')->size($compressedPath);

            // Generate thumbnail (400x400, cover mode)
            $thumbnail = clone $image;
            $thumbnail->cover(400, 400);
            
            $thumbnailFilename = $pathInfo['filename'] . '_thumb.jpg';
            $thumbnailPath = $pathInfo['dirname'] . '/' . $thumbnailFilename;
            $thumbnailData = $thumbnail->toJpeg(80);
            Storage::disk('public')->put($thumbnailPath, $thumbnailData);

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

            Log::info("Image compression completed", [
                'attachment_id' => $attachment->id,
                'original_size' => $originalSize,
                'compressed_size' => $compressedSize,
                'reduction' => round((1 - ($compressedSize / $originalSize)) * 100, 2) . '%',
            ]);

        } catch (\Exception $e) {
            Log::error("Image compression failed", [
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
     * Get JPEG quality based on compression level
     */
    private function getQualityForLevel(string $level): int
    {
        return match ($level) {
            'low' => 60,
            'high' => 90,
            default => 75, // medium
        };
    }

    /**
     * Get max dimensions based on compression level
     */
    private function getMaxDimensions(string $level): array
    {
        return match ($level) {
            'low' => ['width' => 1920, 'height' => 1920],
            'high' => ['width' => 2560, 'height' => 2560],
            default => ['width' => 2048, 'height' => 2048], // medium
        };
    }
}
