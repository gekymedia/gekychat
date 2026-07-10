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

        $rows = [
            $this->row(
                'app_version_android_in_app_update_enabled',
                config('app_versions.android_in_app_update.enabled', false) ? '1' : '0',
                'boolean',
                'Enable Google Play In-App Updates on Android'
            ),
            $this->row(
                'app_version_android_in_app_update_priority',
                config('app_versions.android_in_app_update.priority', 'flexible'),
                'string',
                'Android in-app update style: flexible or immediate'
            ),
        ];

        foreach ($rows as $row) {
            $exists = DB::table('system_settings')->where('key', $row['key'])->exists();
            if (! $exists) {
                DB::table('system_settings')->insert($row);
            }
        }
    }

    protected function row(string $key, string $value, string $type, string $description): array
    {
        return [
            'key' => $key,
            'value' => $value,
            'type' => $type,
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
            ->whereIn('key', [
                'app_version_android_in_app_update_enabled',
                'app_version_android_in_app_update_priority',
            ])
            ->delete();
    }
};
