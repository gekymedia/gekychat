<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;

/**
 * PHASE 1: Seed feature flags for Phase 1 features
 * Run with: php artisan db:seed --class=Phase1FeatureFlagsSeeder
 */
class Phase1FeatureFlagsSeeder extends Seeder
{
    public function run(): void
    {
        $flags = [
            [
                'key' => 'stealth_status_viewing',
                'enabled' => true,
                'platform' => 'all',
                'description' => 'Allow users to view statuses without appearing in viewer list',
            ],
            [
                'key' => 'status_download',
                'enabled' => true,
                'platform' => 'all',
                'description' => 'Control status download permissions per status',
            ],
            [
                'key' => 'improved_call_stack',
                'enabled' => true,
                'platform' => 'all',
                'description' => 'Improved call reliability with TURN servers',
            ],
            [
                'key' => 'delete_for_everyone',
                'enabled' => true,
                'platform' => 'all',
                'description' => 'Allow users to delete messages for everyone (within time limit)',
            ],
            [
                'key' => 'ai_presence',
                'enabled' => true,
                'platform' => 'all',
                'description' => 'Enhanced AI presence and usage tracking',
            ],
        ];

        foreach ($flags as $flag) {
            FeatureFlag::updateOrCreate(
                ['key' => $flag['key']],
                $flag
            );
        }
    }
}

