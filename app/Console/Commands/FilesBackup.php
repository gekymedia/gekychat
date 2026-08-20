<?php

namespace App\Console\Commands;

use App\Services\GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Backup applicant documents and passport photos directly to Google Drive.
 * Uses per-file upload (no local ZIP) so it works even when /home disk is nearly full.
 */
class FilesBackup extends Command
{
    protected $signature = 'files:backup
                            {--path= : Backup only one storage/app relative path}
                            {--no-upload : List paths only, skip Google Drive upload}';

    protected $description = 'Backup passport photos, documents, and signatures to Google Drive';

    public function handle(GoogleDriveService $drive): int
    {
        $paths = $this->resolvePaths();
        if ($paths === []) {
            $this->error('No backup paths configured.');

            return 1;
        }

        if ($this->option('no-upload')) {
            foreach ($paths as $relative) {
                $absolute = storage_path('app/'.$relative);
                $exists = is_dir($absolute) ? 'yes' : 'missing';
                $size = is_dir($absolute) ? $this->dirSize($absolute) : 0;
                $this->line(sprintf('  %-40s %s (%s)', $relative, $exists, $this->formatBytes($size)));
            }

            return 0;
        }

        if (! $drive->isConfigured()) {
            $this->warn('Google Drive not configured (missing OAuth credentials).');

            return 1;
        }

        $timestamp = now()->format('Ymd');
        $total = 0;
        $fileTotal = 0;

        foreach ($paths as $relative) {
            $absolute = storage_path('app/'.$relative);
            if (is_dir($absolute)) {
                $fileTotal += $this->countFiles($absolute);
            }
        }

        if ($fileTotal > 0) {
            Cache::put('files_backup:total_files', $fileTotal, now()->addHours(12));
        }

        foreach ($paths as $relative) {
            $absolute = storage_path('app/'.$relative);
            if (! is_dir($absolute)) {
                $this->warn("Skipping missing path: {$relative}");

                continue;
            }

            $driveFolder = 'files/'.$timestamp.'/'.str_replace('/', '_', $relative);
            $this->info("Uploading {$relative} → Drive/{$driveFolder} ...");

            try {
                $count = $drive->uploadDirectory($absolute, $driveFolder);
                $total += $count;
                $this->info("  ✓ {$count} files");
            } catch (\Throwable $e) {
                $this->error("  ✗ {$relative}: ".$e->getMessage());
                Log::error('files:backup path failed', ['path' => $relative, 'error' => $e->getMessage()]);

                if (str_contains($e->getMessage(), 'insufficient') || str_contains($e->getMessage(), 'scope')) {
                    $this->warn('Re-connect Google at /admin/google/start to grant Drive access.');

                    return 1;
                }
            }
        }

        $this->info("Files backup complete: {$total} files uploaded to Google Drive.");

        return 0;
    }

    /**
     * @return list<string>
     */
    protected function resolvePaths(): array
    {
        $single = $this->option('path');
        if (is_string($single) && $single !== '') {
            return [$single];
        }

        return (array) config('services.google_backup.backup_paths', []);
    }

    protected function countFiles(string $dir): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    protected function dirSize(string $dir): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }
}
