<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserPrivacySetting;
use Illuminate\Database\Seeder;

class UserPrivacySettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates default privacy settings for all existing users
     */
    public function run(): void
    {
        $this->command->info('Creating default privacy settings for existing users...');
        
        $usersWithoutSettings = User::whereDoesntHave('privacySettings')->get();
        $count = 0;
        
        foreach ($usersWithoutSettings as $user) {
            UserPrivacySetting::create([
                'user_id' => $user->id,
                // Default to WhatsApp-style privacy
                'who_can_message' => 'everyone',
                'who_can_see_profile' => 'everyone',
                'who_can_see_last_seen' => 'everyone',
                'who_can_see_status' => 'contacts',
                'who_can_add_to_groups' => 'everyone',
                'who_can_call' => 'everyone',
                'profile_photo_visibility' => 'everyone',
                'about_visibility' => 'everyone',
                'send_read_receipts' => true,
                'send_typing_indicator' => true,
                'show_online_status' => true,
            ]);
            $count++;
        }
        
        $this->command->info("✅ Created privacy settings for {$count} users");
    }
}
