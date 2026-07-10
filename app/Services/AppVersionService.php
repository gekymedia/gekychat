<?php

namespace App\Services;

use App\Models\SystemSetting;

class AppVersionService
{
    public const PLATFORMS = ['android', 'ios', 'windows', 'macos', 'linux'];

    /**
     * @return array{platform: string, latest_version: string, min_version: string, download_url: ?string}
     */
    public function forPlatform(string $platform): array
    {
        $platform = strtolower(trim($platform));
        if (! in_array($platform, self::PLATFORMS, true)) {
            abort(422, 'Invalid platform. Use: '.implode(', ', self::PLATFORMS));
        }

        $defaults = config("app_versions.platforms.{$platform}", []);

        return [
            'platform' => $platform,
            'latest_version' => (string) $this->setting(
                "app_version_{$platform}_latest",
                $defaults['latest_version'] ?? '1.0.0'
            ),
            'min_version' => (string) $this->setting(
                "app_version_{$platform}_min",
                $defaults['min_version'] ?? '1.0.0'
            ),
            'download_url' => $this->nullableString($this->setting(
                "app_version_{$platform}_download_url",
                $defaults['download_url'] ?? null
            )),
        ];
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
}
