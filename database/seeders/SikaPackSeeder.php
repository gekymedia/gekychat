<?php

namespace Database\Seeders;

use App\Models\Sika\SikaCashoutTier;
use App\Models\Sika\SikaPack;
use Illuminate\Database\Seeder;

class SikaPackSeeder extends Seeder
{
    public function run(): void
    {
        $packs = [
            [
                'name' => 'Starter',
                'description' => 'Perfect for beginners',
                'price_ghs' => 5.00,
                'coins' => 500,
                'bonus_coins' => 50,
                'icon' => 'starter',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Popular',
                'description' => 'Best value for regular users',
                'price_ghs' => 20.00,
                'coins' => 2500,
                'bonus_coins' => 500,
                'icon' => 'popular',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Premium',
                'description' => 'For power users',
                'price_ghs' => 50.00,
                'coins' => 7000,
                'bonus_coins' => 1500,
                'icon' => 'premium',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'VIP',
                'description' => 'Maximum coins, maximum bonus',
                'price_ghs' => 100.00,
                'coins' => 15000,
                'bonus_coins' => 5000,
                'icon' => 'vip',
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($packs as $pack) {
            SikaPack::updateOrCreate(
                ['name' => $pack['name']],
                $pack
            );
        }

        $this->command->info('Created ' . count($packs) . ' Sika coin packs');

        $tiers = [
            [
                'name' => 'Standard Cashout',
                'min_coins' => 1000000,
                'max_coins' => null,
                'ghs_per_million_coins' => 100.00,
                'fee_percent' => 0,
                'fee_flat_ghs' => 0,
                'daily_limit' => 5000000,
                'weekly_limit' => 20000000,
                'monthly_limit' => 50000000,
                'hold_days' => 7,
                'is_active' => true,
            ],
        ];

        foreach ($tiers as $tier) {
            SikaCashoutTier::updateOrCreate(
                ['name' => $tier['name']],
                $tier
            );
        }

        $this->command->info('Created ' . count($tiers) . ' Sika cashout tiers');
    }
}
