<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BotContact;

class BotContactSeeder extends Seeder
{
    /**
     * Seed the default GekyChat AI bot into bot_contacts (admin side).
     * The bot user is created/updated via BotContact::getOrCreateUser().
     * New sign-ups get bots from bot_contacts in User::created.
     */
    public const DEFAULT_BOT_DISPLAY_NAME = 'GekyChat AI';

    public function run(): void
    {
        $gekyBot = BotContact::where('bot_number', '0000000000')->first();

        if (!$gekyBot) {
            $gekyBot = BotContact::create([
                'bot_number' => '0000000000',
                'name' => self::DEFAULT_BOT_DISPLAY_NAME,
                'code' => BotContact::generateCode(),
                'is_active' => true,
                'description' => 'Default system bot for GekyChat',
            ]);

            $gekyBot->getOrCreateUser();

            $this->command->info('Default GekyChat AI created in bot_contacts.');
            $this->command->info("Bot Number: {$gekyBot->bot_number}, Code: {$gekyBot->code}");
        } else {
            $gekyBot->update(['name' => self::DEFAULT_BOT_DISPLAY_NAME]);
            $gekyBot->getOrCreateUser(); // sync name to User
            $this->command->info('GekyChat AI already exists in bot_contacts (name updated).');
        }
    }
}
