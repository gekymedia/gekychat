<?php

namespace App\Jobs;

use App\Models\Attachment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Bakes brightness / contrast / saturation filter values into a video file
 * using FFmpeg's `eq` filter.
 *
 * Expected $filters array shape (matches mobile VideoFilterValues):
 *   ['brightness' => 0.0, 'contrast' => 1.0, 'saturation' => 1.0]
 *
 * FFmpeg eq filter ranges:
 *   brightness: -1.0 … 1.0  (0 = neutral)
 *   contrast:    0.0 … 2.0  (1 = neutral)
 *   saturation:  0.0 … 3.0  (1 = neutral)
 */
class ProcessVideoFilters implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $attachmentId;
    public array $filters;

    public function __construct(int $attachmentId, array $filters)
    {
        $this->attachmentId = $attachmentId;
        $this->filters      = $filters;
    }

    public function handle(): void
    {
        $attachment = Attachment::find($this->attachmentId);
        if (!$attachment || !$attachment->file_path) {
            return;
        }

        $ffmpeg = $this->getFfmpegPath();
        if (!$ffmpeg) {
            Log::error('ProcessVideoFilters: FFmpeg not available');
            return;
        }

        $srcPath = Storage::disk('public')->path($attachment->file_path);
        if (!file_exists($srcPath)) return;

        $brightness = (float) ($this->filters['brightness'] ?? 0.0);
        $contrast   = (float) ($this->filters['contrast']   ?? 1.0);
        $saturation = (float) ($this->filters['saturation'] ?? 1.0);

        // Skip if no effective change
        if (abs($brightness) < 0.01 && abs($contrast - 1.0) < 0.01 && abs($saturation - 1.0) < 0.01) {
            return;
        }

        $dir      = dirname($srcPath);
        $baseName = pathinfo($srcPath, PATHINFO_FILENAME);
        $outPath  = $dir . '/' . $baseName . '_filtered.mp4';

        $eqFilter = sprintf(
            'eq=brightness=%.3f:contrast=%.3f:saturation=%.3f',
            $brightness,
            $contrast,
            $saturation,
        );

        $cmd = sprintf(
            '%s -i %s -vf %s -c:a copy %s -y',
            escapeshellarg($ffmpeg),
            escapeshellarg($srcPath),
            escapeshellarg($eqFilter),
            escapeshellarg($outPath),
        );

        try {
            $result = Process::timeout(300)->run($cmd);
            if (!$result->successful()) {
                throw new \RuntimeException('FFmpeg eq failed: ' . $result->errorOutput());
            }

            Storage::disk('public')->delete($attachment->file_path);
            rename($outPath, $srcPath);

            $attachment->update([
                'compression_status' => 'completed',
                'size'               => filesize($srcPath),
                'filter_applied'     => true,
            ]);

            Log::info("ProcessVideoFilters: completed for attachment {$this->attachmentId}");
        } catch (\Throwable $e) {
            Log::error("ProcessVideoFilters: failed for attachment {$this->attachmentId}: " . $e->getMessage());
            @unlink($outPath);
        }
    }

    private function getFfmpegPath(): ?string
    {
        foreach (['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg'] as $p) {
            $r = Process::run('command -v ' . escapeshellarg($p));
            if ($r->successful()) return trim($r->output()) ?: $p;
        }
        return null;
    }
}
