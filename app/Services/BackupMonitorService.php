<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class BackupMonitorService
{
    public function snapshot(): array
    {
        $driveFolder = (string) config('services.google_backup.drive_backup_folder', 'CUG Portal Backups');

        $filesRunning = $this->isArtisanRunning('files:backup');
        $databaseRunning = $this->isArtisanRunning('database:backup');

        $filesLog = $this->mergeLogTails([
            storage_path('logs/files-backup.log'),
        ], 50);

        $databaseLog = $this->tailLog(storage_path('logs/database-backup.log'), 50);

        $latestDbZip = $this->latestLocalDatabaseBackup();
        $uploadProgress = $filesRunning ? $this->estimateFilesUploadProgress() : null;

        [$diskFree, $diskTotal, $diskUsedPct] = $this->diskUsage(storage_path());

        return [
            'drive_configured' => $this->isDriveConfigured(),
            'drive_folder' => $driveFolder,
            'drive_search_url' => 'https://drive.google.com/drive/search?q='.urlencode($driveFolder),
            'files_running' => $filesRunning,
            'database_running' => $databaseRunning,
            'any_running' => $filesRunning || $databaseRunning,
            'schedule' => [
                'database' => 'Daily at 01:15 (Africa/Accra)',
                'files' => 'Weekly on Sunday at 02:00 (Africa/Accra)',
            ],
            'last_database_backup' => $latestDbZip,
            'last_files_backup_line' => $this->lastMatchingLine($filesLog, '/complete|uploaded|Uploading/i'),
            'upload_progress' => $uploadProgress,
            'disk' => [
                'free_bytes' => $diskFree,
                'total_bytes' => $diskTotal,
                'used_percent' => $diskUsedPct,
                'free_human' => $this->formatBytes($diskFree),
                'total_human' => $this->formatBytes($diskTotal),
            ],
            'local_backups' => $this->localDatabaseBackups(5),
            'files_log' => $filesLog,
            'database_log' => $databaseLog,
            'checked_at' => now()->toDateTimeString(),
        ];
    }

    protected function isArtisanRunning(string $command): bool
    {
        if (! function_exists('exec')) {
            return false;
        }

        $pattern = '[a]rtisan '.$command;
        $output = [];
        exec('ps aux 2>/dev/null | grep '.escapeshellarg($pattern), $output);

        return count($output) > 0;
    }

    /**
     * @return list<string>
     */
    protected function mergeLogTails(array $paths, int $lines): array
    {
        $merged = [];
        foreach ($paths as $path) {
            foreach ($this->tailLog($path, $lines) as $line) {
                $merged[] = $line;
            }
        }

        return array_values(array_slice($merged, -$lines));
    }

    /**
     * @return list<string>
     */
    protected function tailLog(string $path, int $lines = 50): array
    {
        if (! $this->isSafeReadable($path)) {
            return [];
        }

        $size = @filesize($path);
        if (! is_int($size) || $size === 0) {
            return [];
        }

        if ($size > 512 * 1024) {
            return $this->tailLogFromEnd($path, $lines);
        }

        $content = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! is_array($content) || $content === []) {
            return [];
        }

        return array_values(array_slice($content, -$lines));
    }

    /**
     * @return list<string>
     */
    protected function tailLogFromEnd(string $path, int $lines): array
    {
        $handle = @fopen($path, 'rb');
        if (! $handle) {
            return [];
        }

        $chunkSize = 8192;
        $buffer = '';
        $lineCount = 0;

        if (@fseek($handle, 0, SEEK_END) !== 0) {
            fclose($handle);

            return [];
        }

        $position = (int) ftell($handle);
        while ($position > 0 && $lineCount <= $lines) {
            $readSize = min($chunkSize, $position);
            $position -= $readSize;
            if (@fseek($handle, $position) !== 0) {
                break;
            }

            $chunk = (string) fread($handle, $readSize);
            $buffer = $chunk.$buffer;
            $lineCount = substr_count($buffer, "\n");
        }

        fclose($handle);

        $rows = array_values(array_filter(explode("\n", $buffer), static fn (string $line): bool => $line !== ''));

        return array_slice($rows, -$lines);
    }

    protected function isDriveConfigured(): bool
    {
        return filled(config('services.google_backup.client_id'))
            && filled(config('services.google_backup.client_secret'))
            && filled(config('services.google_backup.refresh_token'));
    }

    /**
     * Avoid open_basedir warnings when probing paths outside PHP's allowed tree.
     */
    protected function isSafeReadable(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        try {
            return @is_readable($path);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function lastMatchingLine(array $lines, string $pattern): ?string
    {
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (preg_match($pattern, $lines[$i])) {
                return $lines[$i];
            }
        }

        return null;
    }

    /**
     * @return array{filename: string, size_human: string, modified_at: string}|null
     */
    protected function latestLocalDatabaseBackup(): ?array
    {
        $files = glob(storage_path('app/backups/db_backup_*.zip')) ?: [];
        if ($files === []) {
            return null;
        }

        rsort($files, SORT_STRING);

        return $this->describeBackupFile($files[0]);
    }

    /**
     * @return list<array{filename: string, size_human: string, modified_at: string}>
     */
    protected function localDatabaseBackups(int $limit): array
    {
        $files = glob(storage_path('app/backups/db_backup_*.zip')) ?: [];
        rsort($files, SORT_STRING);

        return array_values(array_map(
            fn (string $path) => $this->describeBackupFile($path),
            array_slice($files, 0, $limit)
        ));
    }

    /**
     * @return array{filename: string, size_human: string, modified_at: string}
     */
    protected function describeBackupFile(string $path): array
    {
        $mtime = @filemtime($path) ?: time();

        return [
            'filename' => basename($path),
            'size_human' => $this->formatBytes((int) (@filesize($path) ?: 0)),
            'modified_at' => Carbon::createFromTimestamp($mtime)->toDateTimeString(),
        ];
    }

    /**
     * Rough progress while files:backup runs (counts Drive upload debug lines today).
     *
     * @return array{uploaded: int, total: int, percent: int, current_path: string|null}|null
     */
    protected function estimateFilesUploadProgress(): ?array
    {
        $uploaded = $this->countDriveUploadsToday();

        $total = (int) Cache::get('files_backup:total_files', 0);

        $filesLog = $this->mergeLogTails([
            storage_path('logs/files-backup.log'),
        ], 20);
        $currentPath = null;
        foreach (array_reverse($filesLog) as $line) {
            if (preg_match('/Uploading\s+(\S+)/i', $line, $m)) {
                $currentPath = $m[1];
                break;
            }
        }

        if ($total === 0 && $uploaded === 0 && $currentPath === null) {
            return null;
        }

        $percent = $total > 0 ? min(100, (int) round(($uploaded / $total) * 100)) : 0;

        return [
            'uploaded' => $uploaded,
            'total' => $total,
            'percent' => $percent,
            'current_path' => $currentPath,
        ];
    }

    protected function countDriveUploadsToday(): int
    {
        $logPath = storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');
        if (! $this->isSafeReadable($logPath)) {
            return 0;
        }

        $handle = @fopen($logPath, 'rb');
        if (! $handle) {
            return 0;
        }

        $uploaded = 0;
        while (($line = fgets($handle)) !== false) {
            if (str_contains($line, 'Google Drive file uploaded')) {
                $uploaded++;
            }
        }

        fclose($handle);

        return $uploaded;
    }

    /**
     * @return array{0: int, 1: int, 2: float|null}
     */
    protected function diskUsage(string $path): array
    {
        $free = (int) (disk_free_space($path) ?: 0);
        $total = (int) (disk_total_space($path) ?: 0);
        $usedPct = $total > 0 ? round((($total - $free) / $total) * 100, 1) : null;

        return [$free, $total, $usedPct];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
