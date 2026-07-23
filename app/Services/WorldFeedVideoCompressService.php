<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 1: compress World Feed videos to H.264 + AAC with faststart for instant playback.
 */
class WorldFeedVideoCompressService
{
    /**
     * @return array{
     *   path_720: ?string,
     *   path_480: ?string,
     *   thumbnail: ?string,
     *   duration: ?int
     * }|null
     */
    public function compress(string $videoStoragePath, string $disk = 'public'): ?array
    {
        $ffmpeg = $this->getFfmpegPath();
        if (! $ffmpeg) {
            Log::error('WorldFeedVideoCompress: FFmpeg not found');

            return null;
        }

        $fullVideoPath = Storage::disk($disk)->path($videoStoragePath);
        if (! is_file($fullVideoPath)) {
            Log::error('WorldFeedVideoCompress: video missing', ['path' => $fullVideoPath]);

            return null;
        }

        $dir = dirname($videoStoragePath);
        $base = pathinfo($videoStoragePath, PATHINFO_FILENAME);
        // Strip prior ladder suffixes if re-processing
        $base = preg_replace('/_(?:720|480|compressed|watermarked)$/', '', $base) ?: $base;

        $rel720 = ($dir === '.' ? '' : $dir.'/').$base.'_720.mp4';
        $rel480 = ($dir === '.' ? '' : $dir.'/').$base.'_480.mp4';
        $relThumb = ($dir === '.' ? '' : $dir.'/').$base.'_poster.jpg';

        $full720 = Storage::disk($disk)->path($rel720);
        $full480 = Storage::disk($disk)->path($rel480);
        $fullThumb = Storage::disk($disk)->path($relThumb);

        $ok720 = $this->runTranscode($ffmpeg, $fullVideoPath, $full720, 720, '2000k', 23);
        if (! $ok720) {
            return null;
        }

        $ok480 = $this->runTranscode($ffmpeg, $fullVideoPath, $full480, 480, '800k', 26);
        if (! $ok480) {
            Log::warning('WorldFeedVideoCompress: 480p failed; continuing with 720 only', [
                'source' => $videoStoragePath,
            ]);
            $rel480 = null;
        }

        $thumb = $this->runPoster($ffmpeg, $full720, $fullThumb) ? $relThumb : null;
        $duration = $this->probeDurationSeconds($full720);

        return [
            'path_720' => $rel720,
            'path_480' => $rel480,
            'thumbnail' => $thumb,
            'duration' => $duration,
        ];
    }

    private function runTranscode(
        string $ffmpeg,
        string $input,
        string $output,
        int $maxHeight,
        string $maxRate,
        int $crf
    ): bool {
        $rateNum = (int) filter_var($maxRate, FILTER_SANITIZE_NUMBER_INT);
        $bufsize = ($rateNum * 2).'k';
        // scale keeps aspect; faststart moves moov atom for progressive playback.
        $command = sprintf(
            '%s -y -i %s -c:v libx264 -preset veryfast -crf %d -maxrate %s -bufsize %s -vf "scale=-2:\'min(%d,ih)\':force_original_aspect_ratio=decrease" -c:a aac -b:a 96k -ac 2 -movflags +faststart %s',
            escapeshellarg($ffmpeg),
            escapeshellarg($input),
            $crf,
            escapeshellarg($maxRate),
            escapeshellarg($bufsize),
            $maxHeight,
            escapeshellarg($output)
        );

        $process = Process::timeout(900)->run($command);
        if (! $process->successful() || ! is_file($output) || filesize($output) < 1024) {
            Log::error('WorldFeedVideoCompress: transcode failed', [
                'height' => $maxHeight,
                'error' => $process->errorOutput(),
                'output' => $output,
            ]);
            if (is_file($output)) {
                @unlink($output);
            }

            return false;
        }

        return true;
    }

    private function runPoster(string $ffmpeg, string $videoPath, string $thumbPath): bool
    {
        $command = sprintf(
            '%s -y -ss 00:00:00.5 -i %s -vframes 1 -vf "scale=720:-2" -q:v 3 %s',
            escapeshellarg($ffmpeg),
            escapeshellarg($videoPath),
            escapeshellarg($thumbPath)
        );
        $process = Process::timeout(60)->run($command);
        if (! $process->successful() || ! is_file($thumbPath)) {
            Log::warning('WorldFeedVideoCompress: poster failed', [
                'error' => $process->errorOutput(),
            ]);

            return false;
        }

        return true;
    }

    private function probeDurationSeconds(string $videoPath): ?int
    {
        $ffprobe = $this->getFfprobePath();
        if (! $ffprobe) {
            return null;
        }
        $command = sprintf(
            '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s',
            escapeshellarg($ffprobe),
            escapeshellarg($videoPath)
        );
        $process = Process::timeout(30)->run($command);
        if (! $process->successful()) {
            return null;
        }
        $raw = trim($process->output());
        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return max(1, (int) round((float) $raw));
    }

    private function getFfmpegPath(): ?string
    {
        $configured = config('app.ffmpeg_path') ?: config('world_feed.ffmpeg_path');
        if (is_string($configured) && $configured !== '' && $this->binaryWorks($configured)) {
            return $configured;
        }
        foreach (['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg'] as $p) {
            if ($this->binaryWorks($p)) {
                return $p;
            }
        }

        return null;
    }

    private function getFfprobePath(): ?string
    {
        $configured = config('app.ffprobe_path') ?: config('world_feed.ffprobe_path');
        if (is_string($configured) && $configured !== '' && $this->binaryWorks($configured)) {
            return $configured;
        }
        foreach (['ffprobe', '/usr/bin/ffprobe', '/usr/local/bin/ffprobe'] as $p) {
            if ($this->binaryWorks($p)) {
                return $p;
            }
        }

        return null;
    }

    private function binaryWorks(string $bin): bool
    {
        try {
            $process = Process::timeout(5)->run(escapeshellarg($bin).' -version');

            return $process->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
