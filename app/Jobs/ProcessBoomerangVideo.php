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
 * Processes a video attachment into a boomerang loop:
 *   original → forward + reverse concatenated (no audio)
 *
 * FFmpeg pipeline:
 *   1. Reverse the clip: -vf reverse -an
 *   2. Concat forward + reversed using the concat demuxer
 *   3. Overwrite the attachment's stored file with the result
 */
class ProcessBoomerangVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $attachmentId;

    public function __construct(int $attachmentId)
    {
        $this->attachmentId = $attachmentId;
    }

    public function handle(): void
    {
        $attachment = Attachment::find($this->attachmentId);
        if (!$attachment || !$attachment->file_path) {
            Log::error("ProcessBoomerangVideo: attachment {$this->attachmentId} not found");
            return;
        }

        $ffmpeg = $this->getFfmpegPath();
        if (!$ffmpeg) {
            Log::error('ProcessBoomerangVideo: FFmpeg not available');
            return;
        }

        $srcPath  = Storage::disk('public')->path($attachment->file_path);
        if (!file_exists($srcPath)) {
            Log::error("ProcessBoomerangVideo: source file not found at {$srcPath}");
            return;
        }

        $dir        = dirname($srcPath);
        $baseName   = pathinfo($srcPath, PATHINFO_FILENAME);
        $reversedPath  = $dir . '/' . $baseName . '_rev.mp4';
        $boomerangPath = $dir . '/' . $baseName . '_boomerang.mp4';
        $concatList    = $dir . '/' . $baseName . '_list.txt';

        try {
            // Step 1 – Create reversed clip (drop audio for seamless loop)
            $revCmd = sprintf(
                '%s -i %s -vf reverse -an %s -y',
                escapeshellarg($ffmpeg),
                escapeshellarg($srcPath),
                escapeshellarg($reversedPath),
            );
            $result = Process::timeout(300)->run($revCmd);
            if (!$result->successful()) {
                throw new \RuntimeException('Reverse step failed: ' . $result->errorOutput());
            }

            // Step 2 – Concat list
            file_put_contents($concatList,
                "file " . escapeshellarg($srcPath) . "\n" .
                "file " . escapeshellarg($reversedPath) . "\n"
            );

            // Step 3 – Concatenate forward + reversed
            $concatCmd = sprintf(
                '%s -f concat -safe 0 -i %s -c copy %s -y',
                escapeshellarg($ffmpeg),
                escapeshellarg($concatList),
                escapeshellarg($boomerangPath),
            );
            $result = Process::timeout(300)->run($concatCmd);
            if (!$result->successful()) {
                throw new \RuntimeException('Concat step failed: ' . $result->errorOutput());
            }

            // Step 4 – Replace original file with boomerang
            $relPath = $attachment->file_path;
            Storage::disk('public')->delete($relPath);
            rename($boomerangPath, $srcPath);

            $attachment->update([
                'is_boomerang'         => true,
                'compression_status'   => 'completed',
                'size'                 => filesize($srcPath),
            ]);

            Log::info("ProcessBoomerangVideo: completed for attachment {$this->attachmentId}");
        } catch (\Throwable $e) {
            Log::error("ProcessBoomerangVideo: failed for attachment {$this->attachmentId}: " . $e->getMessage());
            $attachment->update(['compression_status' => 'failed']);
        } finally {
            // Cleanup temp files
            @unlink($reversedPath);
            @unlink($concatList);
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
