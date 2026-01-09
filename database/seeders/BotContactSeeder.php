<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BotContact;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class BotContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default GekyBot if it doesn't exist
        $gekyBot = BotContact::where('bot_number', '0000000000')->first();
        
        if (!$gekyBot) {
            $gekyBot = BotContact::create([
                'bot_number' => '0000000000',
                'name' => 'GekyBot',
                'code' => BotContact::generateCode(),
                'is_active' => true,
                'description' => 'Default system bot for GekyChat',
            ]);

            // Create or update associated user
            $user = User::where('phone', '0000000000')->first();
            if (!$user) {
                User::create([
                    'phone' => '0000000000',
                    'name' => 'GekyBot',
                    'email' => 'bot_0000000000@gekychat.com',
                    'password' => Hash::make(\Illuminate\Support\Str::random(32)),
                    'phone_verified_at' => now(),
                ]);
            } else {
                $user->update(['name' => 'GekyBot']);
            }

            $this->command->info('Default GekyBot created successfully!');
            $this->command->info("Bot Number: {$gekyBot->bot_number}");
            $this->command->info("Code: {$gekyBot->code}");
        } else {
            $this->command->info('GekyBot already exists.');
        }
    }
}
