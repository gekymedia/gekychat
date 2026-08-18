<?php

namespace App\Services;

use App\Models\SystemSetting;

class AppVersionService
{
    public const PLATFORMS = ['android', 'ios', 'windows', 'macos', 'linux'];

    /**
     * @return array<string, string>
     */
    public static function platformLabels(): array
    {
        return [
            'android' => 'Android',
            'ios' => 'iOS',
            'windows' => 'Windows',
            'macos' => 'macOS',
            'linux' => 'Linux',
        ];
    }

    /**
     * @return array<string, array{platform: string, latest_version: string, min_version: string, download_url: ?string}>
     */
    public function allForAdmin(): array
    {
        $result = [];
        foreach (self::PLATFORMS as $platform) {
            $result[$platform] = $this->forPlatform($platform);
        }

        return $result;
    }

    /**
     * @return array{enabled: bool, priority: string}
     */
    public function androidInAppUpdateSettings(): array
    {
        return [
            'enabled' => (bool) $this->setting(
                'app_version_android_in_app_update_enabled',
                config('app_versions.android_in_app_update.enabled', false)
            ),
            'priority' => (string) $this->setting(
                'app_version_android_in_app_update_priority',
                config('app_versions.android_in_app_update.priority', 'flexible')
            ),
        ];
    }

    /**
     * @return array{platform: string, latest_version: string, min_version: string, download_url: ?string, in_app_update_enabled?: bool, in_app_update_priority?: string}
     */
    public function forPlatform(string $platform): array
    {
        $platform = strtolower(trim($platform));
        if (! in_array($platform, self::PLATFORMS, true)) {
            abort(422, 'Invalid platform. Use: '.implode(', ', self::PLATFORMS));
        }

        $defaults = config("app_versions.platforms.{$platform}", []);

        $payload = [
            'platform' => $platform,
            'latest_version' => (string) $this->setting(
                "app_version_{$platform}_latest",
                $defaults['latest_version'] ?? '1.0.0'
            ),
            'min_version' => (string) $this->setting(
                "app_version_{$platform}_min",
                $defaults['min_version'] ?? '1.0.0'
            ),
            'download_url' => $this->resolveDownloadUrl($platform, $defaults),
        ];

        if ($platform === 'android') {
            $android = $this->androidInAppUpdateSettings();
            $payload['in_app_update_enabled'] = $android['enabled'];
            $payload['in_app_update_priority'] = $android['priority'];
        }

        return $payload;
    }

    /**
     * Update only latest_version for one platform (deploy hook / CI).
     *
     * @return array{platform: string, latest_version: string, min_version: string, download_url: ?string}
     */
    public function updateLatestVersion(string $platform, string $latestVersion): array
    {
        $platform = strtolower(trim($platform));
        if (! in_array($platform, self::PLATFORMS, true)) {
            abort(422, 'Invalid platform. Use: '.implode(', ', self::PLATFORMS));
        }

        if (! preg_match('/^\d+\.\d+\.\d+(\+\d+)?$/', $latestVersion)) {
            abort(422, 'Invalid version format. Use semver with optional build, e.g. 1.0.1+101');
        }

        SystemSetting::setValue(
            "app_version_{$platform}_latest",
            $latestVersion,
            'string',
            'app_versions'
        );

        SystemSetting::clearCache();

        return $this->forPlatform($platform);
    }

    public function saveAdminSettings(array $validated): void
    {
        foreach (self::PLATFORMS as $platform) {
            SystemSetting::setValue(
                "app_version_{$platform}_latest",
                $validated["{$platform}_latest_version"],
                'string',
                'app_versions'
            );
            SystemSetting::setValue(
                "app_version_{$platform}_min",
                $validated["{$platform}_min_version"],
                'string',
                'app_versions'
            );
            SystemSetting::setValue(
                "app_version_{$platform}_download_url",
                $validated["{$platform}_download_url"] ?? '',
                'string',
                'app_versions'
            );
        }

        $enabled = filter_var(
            $validated['android_in_app_update_enabled'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        SystemSetting::setValue(
            'app_version_android_in_app_update_enabled',
            $enabled ? '1' : '0',
            'boolean',
            'app_versions'
        );

        $priority = $validated['android_in_app_update_priority'] ?? 'flexible';
        if (! in_array($priority, ['flexible', 'immediate'], true)) {
            $priority = 'flexible';
        }

        SystemSetting::setValue(
            'app_version_android_in_app_update_priority',
            $priority,
            'string',
            'app_versions'
        );

        SystemSetting::clearCache();
    }

    protected function setting(string $key, mixed $default): mixed
    {
        $value = SystemSetting::getValue($key, $default);

        return $value === null || $value === '' ? $default : $value;
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    protected function resolveDownloadUrl(string $platform, array $defaults): ?string
    {
        $downloadUrl = $this->nullableString($this->setting(
            "app_version_{$platform}_download_url",
            $defaults['download_url'] ?? null
        ));

        if ($downloadUrl !== null) {
            return $downloadUrl;
        }

        return match ($platform) {
            'android' => config('app.play_store_url'),
            'ios' => config('app.app_store_url'),
            'windows' => config('app.windows_download_url'),
            'macos' => config('app.macos_download_url'),
            'linux' => config('app.linux_download_url'),
            default => null,
        };
    }
}
