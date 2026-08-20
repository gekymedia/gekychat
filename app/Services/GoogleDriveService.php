<?php

namespace App\Services;

use App\Exceptions\GoogleAuthException;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Upload backups to Google Drive using the same OAuth refresh token as Contacts/Gmail.
 */
class GoogleDriveService
{
    protected Client $client;

    protected ?Drive $drive = null;

    public function __construct()
    {
        $this->bootstrapClient();
    }

    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.refresh_token'));
    }

    /**
     * Upload a local file to Drive under backups/{subfolder}/.
     *
     * @return string|null Drive file ID
     */
    public function uploadBackup(string $localPath, string $remoteFileName, string $subfolder = 'database'): ?string
    {
        if (! is_file($localPath)) {
            throw new \InvalidArgumentException('Backup file not found: '.$localPath);
        }

        $rootFolderId = $this->ensureFolder(
            (string) config('services.google.drive_backup_folder', 'CUG Portal Backups')
        );
        $targetFolderId = $this->ensureFolder($subfolder, $rootFolderId);

        return $this->uploadLocalFile($localPath, $remoteFileName, $targetFolderId);
    }

    /**
     * Mirror a local directory tree to Drive (no local ZIP — safe when disk is tight).
     *
     * @return int Number of files uploaded
     */
    public function uploadDirectory(string $localDir, string $driveSubfolder): int
    {
        if (! is_dir($localDir)) {
            return 0;
        }

        $rootFolderId = $this->ensureFolder(
            (string) config('services.google.drive_backup_folder', 'CUG Portal Backups')
        );
        $targetRootId = $this->ensureFolder($driveSubfolder, $rootFolderId);

        $count = 0;
        $baseLen = strlen(rtrim($localDir, DIRECTORY_SEPARATOR)) + 1;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($localDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($item->getPathname(), $baseLen));
            $parts = explode('/', $relative);
            $fileName = array_pop($parts);

            $parentId = $targetRootId;
            foreach ($parts as $segment) {
                $parentId = $this->ensureFolder($segment, $parentId);
            }

            $this->uploadLocalFile($item->getPathname(), $fileName, $parentId);
            $count++;
        }

        Log::info('Google Drive directory backup complete', [
            'local' => $localDir,
            'drive_subfolder' => $driveSubfolder,
            'files' => $count,
        ]);

        return $count;
    }

    protected function uploadLocalFile(string $localPath, string $remoteFileName, string $parentFolderId): string
    {
        $this->validateToken();
        $drive = $this->drive();

        $metadata = new DriveFile([
            'name' => $remoteFileName,
            'parents' => [$parentFolderId],
        ]);

        $mime = match (strtolower(pathinfo($remoteFileName, PATHINFO_EXTENSION))) {
            'zip' => 'application/zip',
            'sql' => 'application/sql',
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };

        $created = $drive->files->create($metadata, [
            'data' => file_get_contents($localPath),
            'mimeType' => $mime,
            'uploadType' => 'multipart',
            'fields' => 'id,name,size',
        ]);

        Log::debug('Google Drive file uploaded', [
            'file' => $remoteFileName,
            'drive_id' => $created->getId(),
        ]);

        return $created->getId();
    }

    protected function ensureFolder(string $name, ?string $parentId = null): string
    {
        $cacheKey = 'google_drive_folder:'.sha1($name.':'.($parentId ?? 'root'));
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $drive = $this->drive();
        $escaped = str_replace("'", "\\'", $name);
        $query = "name='{$escaped}' and mimeType='application/vnd.google-apps.folder' and trashed=false";
        if ($parentId) {
            $query .= " and '{$parentId}' in parents";
        }

        $existing = $drive->files->listFiles([
            'q' => $query,
            'spaces' => 'drive',
            'fields' => 'files(id,name)',
            'pageSize' => 1,
        ]);

        $files = $existing->getFiles();
        if (! empty($files)) {
            $id = $files[0]->getId();
            Cache::put($cacheKey, $id, now()->addDays(7));

            return $id;
        }

        $metadata = new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);
        if ($parentId) {
            $metadata->setParents([$parentId]);
        }

        $folder = $drive->files->create($metadata, ['fields' => 'id']);
        $id = $folder->getId();
        Cache::put($cacheKey, $id, now()->addDays(7));

        return $id;
    }

    protected function drive(): Drive
    {
        if (! $this->drive) {
            $this->validateToken();
            $this->drive = new Drive($this->client);
        }

        return $this->drive;
    }

    protected function bootstrapClient(): void
    {
        $this->client = new Client();
        $this->client->setClientId((string) config('services.google.client_id'));
        $this->client->setClientSecret((string) config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.redirect'));
        $this->client->setAccessType('offline');
        $this->client->setScopes(config('services.google.scopes', []));

        $refreshToken = config('services.google.refresh_token');
        if ($refreshToken) {
            $this->client->refreshToken($refreshToken);
        }

        $token = Cache::get(config('services.google.access_token_cache_key', 'google_access_token'));
        if (is_array($token) && ! empty($token['access_token'])) {
            $this->client->setAccessToken($token);
        }
    }

    protected function validateToken(): void
    {
        if ($this->client->getAccessToken() && ! $this->client->isAccessTokenExpired()) {
            return;
        }

        $cached = Cache::get(config('services.google.access_token_cache_key', 'google_access_token'));
        if (is_array($cached) && ! empty($cached['access_token'])) {
            $this->client->setAccessToken($cached);
            if (! $this->client->isAccessTokenExpired()) {
                return;
            }
        }

        $refreshToken = config('services.google.refresh_token');
        if (! $refreshToken) {
            throw new GoogleAuthException('No Google refresh token. Connect at Admin → Google auth.');
        }

        $token = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
        if (isset($token['error'])) {
            throw new GoogleAuthException(
                'Google token refresh failed: '.($token['error_description'] ?? $token['error'])
            );
        }

        $ttl = max(60, (int) ($token['expires_in'] ?? 3600) - 300);
        Cache::put(config('services.google.access_token_cache_key', 'google_access_token'), $token, now()->addSeconds($ttl));
        $this->client->setAccessToken($token);
    }
}
