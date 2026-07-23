<?php

namespace App\Jobs;

use App\Models\WorldFeedPost;
use App\Services\WorldFeedVideoCompressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 1: transcode World Feed videos for fast progressive playback.
 * Updates media_url to 720p faststart MP4; keeps original in media_url_original.
 */
class ProcessWorldFeedVideoCompress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 960;

    public function __construct(public int $postId)
    {
    }

    public function handle(WorldFeedVideoCompressService $compressService): void
    {
        $post = WorldFeedPost::find($this->postId);
        if (! $post || $post->type !== 'video') {
            return;
        }

        $sourceRel = $this->relativeStoragePath(
            $post->getRawOriginal('media_url_original')
                ?: $post->getRawOriginal('media_url')
        );
        if ($sourceRel === null) {
            Log::warning('ProcessWorldFeedVideoCompress: no source path', [
                'post_id' => $this->postId,
            ]);

            return;
        }

        $post->update(['video_processing_status' => 'processing']);

        $result = $compressService->compress($sourceRel, 'public');
        if ($result === null || empty($result['path_720'])) {
            $post->update(['video_processing_status' => 'failed']);
            Log::error('ProcessWorldFeedVideoCompress: compress failed', [
                'post_id' => $this->postId,
                'source' => $sourceRel,
            ]);

            return;
        }

        $updates = [
            'media_url_original' => $post->getRawOriginal('media_url_original') ?: $sourceRel,
            'media_url' => $result['path_720'],
            'media_url_480' => $result['path_480'],
            'video_processing_status' => 'ready',
        ];

        if (! empty($result['thumbnail'])) {
            $existingThumb = $post->getRawOriginal('thumbnail_url');
            if (! $existingThumb) {
                $updates['thumbnail_url'] = $result['thumbnail'];
            }
        }
        if (! empty($result['duration']) && empty($post->duration)) {
            $updates['duration'] = $result['duration'];
        }

        $post->update($updates);

        Log::info('ProcessWorldFeedVideoCompress: ready', [
            'post_id' => $this->postId,
            'path_720' => $result['path_720'],
            'path_480' => $result['path_480'],
        ]);

        // Watermark the playback file (not the raw upload) once compress is done.
        if (config('world_feed.watermark_videos', true)) {
            try {
                ProcessWorldFeedVideoWatermark::dispatch($this->postId)
                    ->delay(now()->addSeconds(5));
            } catch (\Throwable $e) {
                Log::warning('ProcessWorldFeedVideoCompress: watermark dispatch failed', [
                    'post_id' => $this->postId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Optional: drop original to save disk (off by default).
        if (config('world_feed.delete_original_after_compress', false)) {
            $original = $post->fresh()?->getRawOriginal('media_url_original');
            $playback = $post->fresh()?->getRawOriginal('media_url');
            if (
                is_string($original)
                && $original !== ''
                && $original !== $playback
                && Storage::disk('public')->exists($original)
            ) {
                Storage::disk('public')->delete($original);
            }
        }
    }

    private function relativeStoragePath(?string $raw): ?string
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        if (str_starts_with($raw, 'http')) {
            if (preg_match('#/storage/(.+)$#', $raw, $m)) {
                return $m[1];
            }

            return null;
        }

        return ltrim($raw, '/');
    }
}
