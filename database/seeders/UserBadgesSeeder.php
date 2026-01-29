<?php

namespace Database\Seeders;

use App\Models\UserBadge;
use Illuminate\Database\Seeder;

class UserBadgesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates default badge types for the platform
     */
    public function run(): void
    {
        $this->command->info('Creating default user badges...');
        
        $badges = [
            [
                'name' => 'verified',
                'display_name' => 'Verified',
                'icon' => '✓',
                'color' => '#3B82F6', // Blue
                'description' => 'This account has been verified by GekyChat',
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'name' => 'early_adopter',
                'display_name' => 'Early Adopter',
                'icon' => '⭐',
                'color' => '#F59E0B', // Amber
                'description' => 'Joined GekyChat in its early days',
                'is_active' => true,
                'display_order' => 2,
            ],
            [
                'name' => 'premium',
                'display_name' => 'Premium',
                'icon' => '👑',
                'color' => '#8B5CF6', // Purple
                'description' => 'GekyChat Premium subscriber',
                'is_active' => true,
                'display_order' => 3,
            ],
            [
                'name' => 'developer',
                'display_name' => 'Developer',
                'icon' => '💻',
                'color' => '#10B981', // Green
                'description' => 'GekyChat API developer',
                'is_active' => true,
                'display_order' => 4,
            ],
            [
                'name' => 'moderator',
                'display_name' => 'Moderator',
                'icon' => '🛡️',
                'color' => '#EF4444', // Red
                'description' => 'Community moderator',
                'is_active' => true,
                'display_order' => 5,
            ],
            [
                'name' => 'business',
                'display_name' => 'Business',
                'icon' => '💼',
                'color' => '#6366F1', // Indigo
                'description' => 'Business account',
                'is_active' => true,
                'display_order' => 6,
            ],
            [
                'name' => 'supporter',
                'display_name' => 'Supporter',
                'icon' => '❤️',
                'color' => '#EC4899', // Pink
                'description' => 'GekyChat supporter',
                'is_active' => true,
                'display_order' => 7,
            ],
        ];
        
        foreach ($badges as $badgeData) {
            UserBadge::updateOrCreate(
                ['name' => $badgeData['name']],
                $badgeData
            );
        }
        
        $this->command->info('✅ Created ' . count($badges) . ' badge types');
    }
}
