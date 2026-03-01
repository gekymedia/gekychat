<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BotContact;

class BotContactSeeder extends Seeder
{
    /**
     * Seed the bot contacts table with all available bots.
     * 
     * Bot Types:
     * - GekyChat AI (0000000000): General assistant, auto-added to all users
     * - CUG Admissions (0000000001): Admission queries, NOT auto-added
     * - BlackTask (0000000002): Task management, NOT auto-added
     */
    
    public const BOTS = [
        [
            'bot_number' => '0000000000',
            'name' => 'GekyChat AI',
            'bot_type' => 'general',
            'auto_add_to_contacts' => true,
            'description' => 'Your friendly AI assistant for general questions and help',
        ],
        [
            'bot_number' => '0000000001',
            'name' => 'CUG Admissions',
            'bot_type' => 'admissions',
            'auto_add_to_contacts' => false,
            'description' => 'Catholic University of Ghana admissions assistant - Get help with applications, programmes, fees, and requirements',
        ],
        [
            'bot_number' => '0000000002',
            'name' => 'BlackTask',
            'bot_type' => 'tasks',
            'auto_add_to_contacts' => false,
            'description' => 'Your personal task manager - Create, manage, and track your todo list',
        ],
    ];

    public function run(): void
    {
        foreach (self::BOTS as $botData) {
            $bot = BotContact::where('bot_number', $botData['bot_number'])->first();

            if (!$bot) {
                $bot = BotContact::create([
                    'bot_number' => $botData['bot_number'],
                    'name' => $botData['name'],
                    'code' => BotContact::generateCode(),
                    'is_active' => true,
                    'auto_add_to_contacts' => $botData['auto_add_to_contacts'],
                    'bot_type' => $botData['bot_type'],
                    'description' => $botData['description'],
                ]);

                $bot->getOrCreateUser();

                $this->command->info("✅ Created bot: {$botData['name']} ({$botData['bot_number']})");
                $this->command->info("   Type: {$botData['bot_type']}, Auto-add: " . ($botData['auto_add_to_contacts'] ? 'Yes' : 'No'));
            } else {
                // Update existing bot
                $bot->update([
                    'name' => $botData['name'],
                    'bot_type' => $botData['bot_type'],
                    'auto_add_to_contacts' => $botData['auto_add_to_contacts'],
                    'description' => $botData['description'],
                ]);
                $bot->getOrCreateUser(); // Sync name to User
                
                $this->command->info("✓ Updated bot: {$botData['name']} ({$botData['bot_number']})");
            }
        }
        
        $this->command->newLine();
        $this->command->info('Bot seeding complete!');
        $this->command->info('Note: Only GekyChat AI is auto-added to new users.');
        $this->command->info('Users can manually add CUG Admissions or BlackTask bots.');
    }
}
