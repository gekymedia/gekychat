<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageStatus;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Make two demo users
        $u1 = User::firstOrCreate(
            ['phone' => '0205440495'],
            [
                'name'              => 'Brother Emmanuel',
                'email'             => 'geky@example.com',
                'password'          => Hash::make('password'),
                'phone_verified_at' => now(),
            ]
        );

        $u2 = User::firstOrCreate(
            ['phone' => '0248229540'],
            [
                'name'              => 'Admissions Bot',
                'email'             => 'bot@example.com',
                'password'          => Hash::make('password'),
                'phone_verified_at' => now(),
            ]
        );

        // Pivot + pair columns via ConversationService
        $conversation = Conversation::findOrCreateDirect($u1->id, $u2->id, $u1->id);

        // Seed some messages if empty
        if (! $conversation->messages()->exists()) {
            $m1 = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $u2->id,
                'body'            => 'Welcome to GekyChat mobile scaffold! 🎉',
                'type'            => 'text',
            ]);

            $m2 = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $u1->id,
                'body'            => 'Thanks! Getting things set up on the app.',
                'type'            => 'text',
            ]);

            // ✅ Seed per-user statuses instead of delivered_at column
            foreach ([$u1, $u2] as $user) {
                // For message 1: it's "sent" for the bot, "delivered" for you
                $m1Status = $user->id === $u2->id
                    ? MessageStatus::STATUS_SENT
                    : MessageStatus::STATUS_DELIVERED;

                $m1->statuses()->create([
                    'user_id' => $user->id,
                    'status'  => $m1Status,
                ]);

                // For message 2: it's "sent" for you, "delivered" for the bot
                $m2Status = $user->id === $u1->id
                    ? MessageStatus::STATUS_SENT
                    : MessageStatus::STATUS_DELIVERED;

                $m2->statuses()->create([
                    'user_id' => $user->id,
                    'status'  => $m2Status,
                ]);
            }
        }
    }
}
