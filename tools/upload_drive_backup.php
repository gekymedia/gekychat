<?php

/**
 * One-off CLI helper: upload a local file to Google Drive backups folder.
 * Usage: php tools/upload_drive_backup.php /path/to/file.zip inbound-archives
 */

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$localPath = $argv[1] ?? '';
$subfolder = $argv[2] ?? 'misc';

if ($localPath === '' || ! is_file($localPath)) {
    fwrite(STDERR, "File not found: {$localPath}\n");
    exit(1);
}

try {
    $drive = app(App\Services\GoogleDriveService::class);
    if (! $drive->isConfigured()) {
        fwrite(STDERR, "Google Drive not configured.\n");
        exit(1);
    }

    $id = $drive->uploadBackup($localPath, basename($localPath), $subfolder);
    if (! $id) {
        fwrite(STDERR, "Upload returned no file id.\n");
        exit(1);
    }

    echo "OK {$id}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: '.$e->getMessage()."\n");
    exit(1);
}
