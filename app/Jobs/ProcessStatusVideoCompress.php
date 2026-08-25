<?php

namespace App\Jobs;

use App\Models\Status;
use App\Services\WorldFeedVideoCompressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Transcode large status videos to 720p H.264 so leftover iPhone camera
 * files (and old clients) do not sit at 50–100 MB for the retention window.
 */
class ProcessStatusVideoCompress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    /** Skip ffmpeg when the upload is already small enough (client 720p). */
    private const SKIP_UNDER_BYTES = 48 * 1024 * 1024;

    public function __construct(public int $statusId)
    {
    }

    public function handle(WorldFeedVideoCompressService $compressService): void
    {
        $status = Status::find($this->statusId);
        if (! $status || $status->type !== 'video') {
            return;
        }

        $sourceRel = $this->relativeStoragePath($status->getRawOriginal('media_url'));
        if ($sourceRel === null) {
            return;
        }

        if (! Storage::disk('public')->exists($sourceRel)) {
            return;
        }

        $size = Storage::disk('public')->size($sourceRel);
        if ($size > 0 && $size < self::SKIP_UNDER_BYTES) {
            return;
        }

        $compressedRel = $compressService->compressSingle($sourceRel, 'public');
        if ($compressedRel === null || $compressedRel === $sourceRel) {
            Log::warning('ProcessStatusVideoCompress: compress failed', [
                'status_id' => $this->statusId,
                'source' => $sourceRel,
            ]);

            return;
        }

        if (! Storage::disk('public')->exists($compressedRel)) {
            return;
        }

        $compressedSize = Storage::disk('public')->size($compressedRel);
        if ($compressedSize < 1024 || ($size > 0 && $compressedSize >= $size)) {
            Storage::disk('public')->delete($compressedRel);

            return;
        }

        $status->update(['media_url' => $compressedRel]);
        Storage::disk('public')->delete($sourceRel);

        Log::info('ProcessStatusVideoCompress: ready', [
            'status_id' => $this->statusId,
            'original_size' => $size,
            'compressed_size' => $compressedSize,
            'path' => $compressedRel,
        ]);
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
