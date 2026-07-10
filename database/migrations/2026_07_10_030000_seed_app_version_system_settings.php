<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $platforms = ['android', 'ios', 'windows', 'macos', 'linux'];
        $rows = [];

        foreach ($platforms as $platform) {
            $config = config("app_versions.platforms.{$platform}", []);
            $rows[] = $this->row("app_version_{$platform}_latest", $config['latest_version'] ?? '1.0.0', "Latest {$platform} app version");
            $rows[] = $this->row("app_version_{$platform}_min", $config['min_version'] ?? '1.0.0', "Minimum required {$platform} app version");
            $rows[] = $this->row("app_version_{$platform}_download_url", $config['download_url'] ?? '', "Download / store URL for {$platform}");
        }

        foreach ($rows as $row) {
            $exists = DB::table('system_settings')->where('key', $row['key'])->exists();
            if (! $exists) {
                DB::table('system_settings')->insert($row);
            }
        }
    }

    protected function row(string $key, string $value, string $description): array
    {
        return [
            'key' => $key,
            'value' => $value,
            'type' => 'string',
            'group' => 'app_versions',
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')
            ->where('group', 'app_versions')
            ->delete();
    }
};
