<?php

namespace App\Console\Commands;

use App\Services\GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Backup the application database to a timestamped ZIP under storage/app/backups.
 * Supports MySQL/MariaDB (mysqldump) and SQLite. Optionally uploads to a Google Drive disk.
 */
class DatabaseBackup extends Command
{
    protected $signature = 'database:backup {--keep=14 : Number of local backup zips to retain}';

    protected $description = 'Backup the application database and optionally upload to Google Drive';

    public function handle(GoogleDriveService $drive): int
    {
        $timestamp = now()->format('Ymd_His');
        $zipName = "db_backup_{$timestamp}.zip";
        $backupDir = storage_path('app/backups');
        $zipPath = $backupDir.'/'.$zipName;

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0750, true);
        }

        $driver = config('database.default');
        $connection = config("database.connections.{$driver}");

        $payloadPath = null;
        $payloadName = null;

        try {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                [$payloadPath, $payloadName] = $this->dumpMysql($connection, $backupDir, $timestamp);
            } elseif ($driver === 'sqlite') {
                [$payloadPath, $payloadName] = $this->copySqlite($connection, $backupDir, $timestamp);
            } else {
                $this->error("Unsupported database driver for backup: {$driver}");

                return 1;
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $this->error('Unable to create ZIP: '.$zipPath);

                return 1;
            }
            $zip->addFile($payloadPath, $payloadName);
            $zip->close();

            @unlink($payloadPath);

            $this->info('Database backup created: '.$zipName);
            $this->pruneOldBackups($backupDir, (int) $this->option('keep'));

            try {
                if ($drive->isConfigured()) {
                    $drive->uploadBackup($zipPath, $zipName, 'database');
                    $this->info('Backup uploaded to Google Drive: database/'.$zipName);
                } else {
                    $this->warn('Google Drive not configured (missing OAuth credentials); backup stored locally.');
                }
            } catch (\Throwable $e) {
                $this->error('Failed to upload backup to Google Drive: '.$e->getMessage());
                Log::error('database:backup upload failed', ['error' => $e->getMessage()]);
                if (str_contains($e->getMessage(), 'insufficient') || str_contains($e->getMessage(), 'scope')) {
                    $this->warn('Re-connect Google at /admin/google/start to grant Drive access.');
                }
            }

            return 0;
        } catch (\Throwable $e) {
            if ($payloadPath && is_file($payloadPath)) {
                @unlink($payloadPath);
            }
            $this->error('Backup failed: '.$e->getMessage());
            Log::error('database:backup failed', ['error' => $e->getMessage()]);

            return 1;
        }
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return array{0: string, 1: string}
     */
    protected function dumpMysql(array $connection, string $backupDir, string $timestamp): array
    {
        $sqlName = "db_backup_{$timestamp}.sql";
        $sqlPath = $backupDir.'/'.$sqlName;

        $host = $connection['host'] ?? '127.0.0.1';
        $port = (string) ($connection['port'] ?? 3306);
        $database = $connection['database'] ?? '';
        $username = $connection['username'] ?? '';
        $password = (string) ($connection['password'] ?? '');

        $dumpBin = $this->findDumpBinary();
        if (! $dumpBin) {
            throw new \RuntimeException('mysqldump/mariadb-dump not found on PATH');
        }

        // Prefer socket/root auth when available; otherwise pass credentials on argv.
        // (MariaDB on this host rejects this DB user's password via defaults-extra-file.)
        if ($this->canDumpAsRoot($dumpBin)) {
            $cmd = sprintf(
                '%s --single-transaction --routines --triggers --databases %s',
                escapeshellcmd($dumpBin),
                escapeshellarg($database)
            );
        } else {
            $cmd = sprintf(
                '%s --host=%s --port=%s --user=%s -p%s --single-transaction --routines --triggers --databases %s',
                escapeshellcmd($dumpBin),
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database)
            );
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $sqlPath, 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptors, $pipes, null, null);
        if (! is_resource($process)) {
            throw new \RuntimeException('Unable to start mysqldump process');
        }
        fclose($pipes[0]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($process);

        if ($code !== 0 || ! is_file($sqlPath) || filesize($sqlPath) < 64) {
            @unlink($sqlPath);
            throw new \RuntimeException(trim((string) $stderr) !== '' ? trim((string) $stderr) : "mysqldump exited with code {$code}");
        }

        return [$sqlPath, $sqlName];
    }

    protected function canDumpAsRoot(string $dumpBin): bool
    {
        if (! function_exists('posix_geteuid') || posix_geteuid() !== 0) {
            return false;
        }

        $cmd = escapeshellcmd($dumpBin).' --single-transaction --no-data '.escapeshellarg('mysql');
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptors, $pipes, null, null);
        if (! is_resource($process)) {
            return false;
        }
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return proc_close($process) === 0;
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return array{0: string, 1: string}
     */
    protected function copySqlite(array $connection, string $backupDir, string $timestamp): array
    {
        $dbPath = $connection['database'] ?? '';
        if (! $dbPath || ! is_file($dbPath)) {
            // Legacy project path fallback
            $dbPath = base_path('gekymedi_cugdb');
        }
        if (! is_file($dbPath)) {
            throw new \RuntimeException('SQLite database file not found: '.$dbPath);
        }

        $copyName = 'gekymedi_cugdb_'.$timestamp;
        $copyPath = $backupDir.'/'.$copyName;
        if (! copy($dbPath, $copyPath)) {
            throw new \RuntimeException('Failed to copy SQLite database');
        }

        return [$copyPath, $copyName];
    }

    protected function findDumpBinary(): ?string
    {
        $paths = [];
        foreach (['mariadb-dump', 'mysqldump'] as $bin) {
            $which = trim((string) @shell_exec('command -v '.$bin.' 2>/dev/null'));
            if ($which !== '') {
                return $which;
            }
            $paths[] = '/usr/bin/'.$bin;
            $paths[] = '/usr/local/bin/'.$bin;
        }
        foreach ($paths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function pruneOldBackups(string $backupDir, int $keep): void
    {
        if ($keep < 1) {
            return;
        }

        $files = glob($backupDir.'/db_backup_*.zip') ?: [];
        rsort($files, SORT_STRING);
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }
    }
}
