<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Server-side video watermarking for World Feed (TikTok-style).
 * Overlays logo (optional) + username/creator name on uploaded videos using FFmpeg.
 * Used during upload/transcode so stored media already has the overlay (no mobile FFmpeg dependency).
 */
class WorldFeedVideoWatermarkService
{
    /**
     * Apply watermark (logo + username text) to a video file.
     * Overwrites the file at $videoStoragePath with the watermarked version.
     *
     * @param string $videoStoragePath Relative path on the given disk (e.g. 'world-feed/worldfeed_xxx.mp4')
     * @param string $creatorName Display name for the creator
     * @param string|null $username Optional username (e.g. @handle); if set, shown as primary text
     * @param string $disk Storage disk name
     * @return bool True if watermark was applied successfully, false on skip/failure
     */
    public function applyWatermark(
        string $videoStoragePath,
        string $creatorName,
        ?string $username = null,
        string $disk = 'public'
    ): bool {
        $ffmpeg = $this->getFfmpegPath();
        if (!$ffmpeg) {
            Log::warning('WorldFeedVideoWatermark: FFmpeg not available');
            return false;
        }

        $fullVideoPath = Storage::disk($disk)->path($videoStoragePath);
        if (!file_exists($fullVideoPath)) {
            Log::error('WorldFeedVideoWatermark: video file not found', ['path' => $fullVideoPath]);
            return false;
        }

        $dir = dirname($fullVideoPath);
        $baseName = pathinfo($fullVideoPath, PATHINFO_FILENAME);
        $ext = pathinfo($fullVideoPath, PATHINFO_EXTENSION) ?: 'mp4';
        $outPath = $dir . '/' . $baseName . '_watermarked.' . $ext;

        // Primary text: @username or creator name (use temp file to avoid shell/filter escaping issues)
        $text = $username !== null && $username !== ''
            ? '@' . ltrim($username, '@')
            : $creatorName;
        $text = trim($text) ?: 'GekyChat';
        $textFile = $dir . '/' . $baseName . '_wmtext.txt';
        file_put_contents($textFile, $text);
        $logoPath = $this->getLogoPath();
        $vf = $this->buildFilterChain($textFile, $logoPath);
        if ($vf === null) {
            @unlink($textFile);
            Log::warning('WorldFeedVideoWatermark: failed to build filter');
            return false;
        }

        try {
            if ($logoPath !== null) {
                $cmd = sprintf(
                    '%s -i %s -i %s -filter_complex %s -map "[v]" -map "0:a?" -c:a copy -y %s',
                    escapeshellarg($ffmpeg),
                    escapeshellarg($fullVideoPath),
                    escapeshellarg($logoPath),
                    escapeshellarg($vf),
                    escapeshellarg($outPath)
                );
            } else {
                $cmd = sprintf(
                    '%s -i %s -vf %s -c:a copy -y %s',
                    escapeshellarg($ffmpeg),
                    escapeshellarg($fullVideoPath),
                    escapeshellarg($vf),
                    escapeshellarg($outPath)
                );
            }

            $result = Process::timeout(600)->run($cmd);
            if (!$result->successful()) {
                Log::error('WorldFeedVideoWatermark: FFmpeg failed', [
                    'stderr' => $result->errorOutput(),
                    'path' => $videoStoragePath,
                ]);
                @unlink($outPath);
                return false;
            }

            if (!file_exists($outPath)) {
                Log::error('WorldFeedVideoWatermark: output file not created');
                return false;
            }

            Storage::disk($disk)->delete($videoStoragePath);
            rename($outPath, $fullVideoPath);

            Log::info('WorldFeedVideoWatermark: applied successfully', ['path' => $videoStoragePath]);
            return true;
        } catch (\Throwable $e) {
            Log::error('WorldFeedVideoWatermark: exception', [
                'path' => $videoStoragePath,
                'error' => $e->getMessage(),
            ]);
            @unlink($outPath);
            return false;
        } finally {
            @unlink($textFile);
        }
    }

    /**
     * Build FFmpeg filter: drawtext for username (bottom-left, with shadow).
     * $textFilePath: path to file containing the text (avoids escaping issues).
     * If logo path is set, overlay logo top-right then drawtext on the result.
     */
    private function buildFilterChain(string $textFilePath, ?string $logoPath): ?string
    {
        $drawtext = sprintf(
            "drawtext=textfile=%s:fontsize=28:fontcolor=white:x=24:y=h-th-24:shadowcolor=black:shadowx=2:shadowy=2",
            str_replace(['\\', ':'], ['\\\\', '\\:'], $textFilePath)
        );

        if ($logoPath === null || !file_exists($logoPath)) {
            return $drawtext;
        }

        // [0:v]=video [1:v]=logo → scale logo, overlay top-right, then drawtext on result
        return sprintf(
            '[1:v]scale=-1:120[logo];[0:v][logo]overlay=w-overlay_w-24:24[v1];[v1]%s[v]',
            $drawtext
        );
    }

    /** Logo path from config (e.g. public_path('images/watermark-logo.png')). */
    private function getLogoPath(): ?string
    {
        $path = config('world_feed.watermark_logo_path');
        if ($path === null || $path === '') {
            return null;
        }
        if (!str_starts_with($path, '/')) {
            $path = public_path($path);
        }
        return file_exists($path) ? $path : null;
    }

    private function getFfmpegPath(): ?string
    {
        $path = config('app.ffmpeg_path');
        if ($path && file_exists($path)) {
            return $path;
        }
        foreach (['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg'] as $p) {
            $r = Process::run('command -v ' . escapeshellarg($p));
            if ($r->successful() && trim($r->output()) !== '') {
                return trim($r->output());
            }
        }
        return null;
    }
}
