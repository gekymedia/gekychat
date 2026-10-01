<?php

namespace App\Console\Commands;

use App\Helpers\UrlHelper;
use App\Models\AudioLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Import CC0 / public-domain MP3 files into audio_library and public storage.
 *
 * Example:
 *   php artisan audio:import-local /tmp/cc0-music/tracks \
 *     --artist="Kevin MacLeod" --source=archive_org_cc0 --license="Creative Commons 0"
 */
class ImportLocalAudioLibrary extends Command
{
    protected $signature = 'audio:import-local
                            {path : Directory containing MP3 files (scanned recursively)}
                            {--artist=Unknown : Artist / creator credit stored on each row}
                            {--source=local_cc0 : Source label (e.g. archive_org_cc0)}
                            {--license=Creative Commons 0 : License type string}
                            {--license-url=https://creativecommons.org/publicdomain/zero/1.0/ : License URL}
                            {--category= : Optional default category when folder name is unavailable}
                            {--dry-run : List files without importing}';

    protected $description = 'Import local CC0/public-domain MP3s into the audio_library for World/Status pickers';

    public function handle(): int
    {
        $root = rtrim((string) $this->argument('path'), DIRECTORY_SEPARATOR);
        if (!is_dir($root)) {
            $this->error("Directory not found: {$root}");
            return 1;
        }

        $files = $this->collectMp3s($root);
        if ($files === []) {
            $this->warn('No MP3 files found.');
            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');
        $artist = (string) $this->option('artist');
        $source = (string) $this->option('source');
        $license = (string) $this->option('license');
        $licenseUrl = (string) $this->option('license-url');
        $defaultCategory = $this->option('category') ?: null;

        $this->info(($dryRun ? '[dry-run] ' : '') . 'Found ' . count($files) . ' MP3 file(s).');

        $imported = 0;
        $skipped = 0;

        foreach ($files as $absolutePath) {
            $relative = ltrim(Str::after($absolutePath, $root . DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
            $name = pathinfo($absolutePath, PATHINFO_FILENAME);
            $category = $defaultCategory;
            if ($category === null && str_contains($relative, DIRECTORY_SEPARATOR)) {
                $category = explode(DIRECTORY_SEPARATOR, $relative)[0];
            }

            $duration = $this->probeDurationSeconds($absolutePath);
            $size = filesize($absolutePath) ?: 0;
            $slug = Str::slug($name);
            if ($slug === '') {
                $slug = 'track-' . substr(sha1($relative), 0, 10);
            }
            $destRel = 'audio-library/' . $source . '/' . $slug . '-' . substr(sha1($relative), 0, 8) . '.mp3';

            $existing = AudioLibrary::where('local_path', $destRel)
                ->orWhere(function ($q) use ($name, $source, $artist) {
                    $q->where('name', $name)
                        ->where('source', $source)
                        ->where('freesound_username', $artist);
                })
                ->first();

            if ($existing && $existing->local_path && Storage::disk('public')->exists($existing->local_path)) {
                $this->line("skip  {$relative}");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    'import %s (%.1fs, %s, cat=%s)',
                    $relative,
                    $duration,
                    $this->humanBytes($size),
                    $category ?? '-'
                ));
                $imported++;
                continue;
            }

            Storage::disk('public')->put($destRel, File::get($absolutePath));

            $snapshot = [
                'name' => $name,
                'username' => $artist,
                'license' => $license,
                'license_url' => $licenseUrl,
                'source' => $source,
                'original_relative_path' => $relative,
                'imported_at' => now()->toIso8601String(),
            ];

            $audio = AudioLibrary::updateOrCreate(
                ['local_path' => $destRel],
                [
                    'freesound_id' => null,
                    'freesound_username' => $artist,
                    'source' => $source,
                    'name' => $name,
                    'description' => "CC0 / public domain track from {$source}",
                    'duration' => $duration,
                    'file_size' => $size,
                    'preview_url' => UrlHelper::secureStorageUrl($destRel, 'public'),
                    'download_url' => null,
                    'license_type' => $license,
                    'license_url' => $licenseUrl,
                    'license_snapshot' => $snapshot,
                    'attribution_required' => false,
                    'attribution_text' => null,
                    'tags' => array_values(array_filter([
                        $category ? Str::slug($category) : null,
                        'cc0',
                        'music',
                        'background',
                    ])),
                    'category' => $category,
                    'cached_at' => now(),
                    'cache_expires_at' => null,
                    'validation_status' => 'approved',
                    'is_active' => true,
                ]
            );

            $this->line("ok    #{$audio->id}  {$relative}");
            $imported++;
        }

        $this->info("Done. imported={$imported} skipped={$skipped}");
        return 0;
    }

    /** @return list<string> */
    private function collectMp3s(string $root): array
    {
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if (strtolower($file->getExtension()) !== 'mp3') {
                continue;
            }
            $out[] = $file->getPathname();
        }
        sort($out);
        return $out;
    }

    private function probeDurationSeconds(string $path): float
    {
        $ffprobe = trim((string) shell_exec('command -v ffprobe'));
        if ($ffprobe !== '') {
            $cmd = sprintf(
                '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
                escapeshellcmd($ffprobe),
                escapeshellarg($path)
            );
            $raw = trim((string) shell_exec($cmd));
            if (is_numeric($raw)) {
                return round((float) $raw, 2);
            }
        }

        // Rough fallback from file size assuming ~128 kbps.
        $size = filesize($path) ?: 0;
        return round(max(1, $size / 16000), 2);
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}
