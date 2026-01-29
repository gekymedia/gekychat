<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\NotificationPreference;
use Illuminate\Database\Seeder;

class NotificationPreferencesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates default notification preferences for all existing users
     */
    public function run(): void
    {
        $this->command->info('Creating default notification preferences for existing users...');
        
        $usersWithoutPreferences = User::whereDoesntHave('notificationPreferences')->get();
        $count = 0;
        
        foreach ($usersWithoutPreferences as $user) {
            NotificationPreference::create([
                'user_id' => $user->id,
                // Push notifications - all enabled by default
                'push_messages' => true,
                'push_group_messages' => true,
                'push_calls' => true,
                'push_status_updates' => true,
                'push_reactions' => true,
                'push_mentions' => true,
                // Email notifications - conservative defaults
                'email_messages' => false,
                'email_weekly_digest' => true,
                'email_security_alerts' => true,
                'email_marketing' => false,
                // In-app notifications
                'show_message_preview' => true,
                'notification_sound' => true,
                'vibrate' => true,
                'led_notification' => true,
                // Quiet hours - disabled by default
                'quiet_hours_start' => null,
                'quiet_hours_end' => null,
                'quiet_hours_enabled' => false,
            ]);
            $count++;
        }
        
        $this->command->info("✅ Created notification preferences for {$count} users");
    }
}
