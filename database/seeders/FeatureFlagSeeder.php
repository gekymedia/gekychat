<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;

class FeatureFlagSeeder extends Seeder
{
    public function run(): void
    {
        $flags = [
            [
                'key' => 'multi_account',
                'enabled' => true,
                'platform' => 'mobile',
                'description' => 'Multi-account support for mobile apps',
            ],
            [
                'key' => 'world_feed',
                'enabled' => true,
                'platform' => 'all',
                'description' => 'World Feed social media feature',
            ],
            [
                'key' => 'live_broadcast',
                'enabled' => true,
                'platform' => 'all',
                'description' => 'Live broadcasting feature',
            ],
            [
                'key' => 'email_chat',
                'enabled' => true,
                'platform' => 'all',
                'description' => 'Email-based chat feature',
            ],
            [
                'key' => 'advanced_ai',
                'enabled' => true,
                'platform' => 'all',
                'description' => 'Advanced AI chat features',
            ],
        ];

        foreach ($flags as $flag) {
            FeatureFlag::updateOrCreate(
                ['key' => $flag['key']],
                $flag
            );
        }

        $this->command->info('✅ Feature flags seeded successfully');
    }
}
