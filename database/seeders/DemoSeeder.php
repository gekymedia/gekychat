<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Make two demo users
        $u1 = User::firstOrCreate(
            ['phone' => '0205440495'],
            [
                'name' => 'Brother Emmanuel',
                'email' => 'geky@example.com',
                'password' => Hash::make('password'),
                'phone_verified_at' => now(),
            ]
        );

        $u2 = User::firstOrCreate(
            ['phone' => '0248229540'],
            [
                'name' => 'Admissions Bot',
                'email' => 'bot@example.com',
                'password' => Hash::make('password'),
                'phone_verified_at' => now(),
            ]
        );

        // Ensure deterministic ordering to avoid duplicate pairs
        $pair = [$u1->id, $u2->id];
        sort($pair);
        [$one, $two] = $pair;

        // Create (or fetch) a DM conversation between them
        $conversation = Conversation::firstOrCreate([
            'user_one_id' => $one,
            'user_two_id' => $two,
        ]);

        // Seed some messages if empty
        if (! $conversation->messages()->exists()) {
            $m1 = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $u2->id,
                'body'            => 'Welcome to GekyChat mobile scaffold! 🎉',
                'delivered_at'    => now(),
            ]);

            $m2 = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $u1->id,
                'body'            => 'Thanks! Getting things set up on the app.',
                'delivered_at'    => now(),
            ]);

            // Optionally mark as delivered using your model helper
            // (this also writes per-user statuses)
            try {
                $m1->markAsDelivered();
                $m2->markAsDelivered();
            } catch (\Throwable $e) {
                // safe no-op if anything about relationships isn't ready in your env
            }
        }
    }
}
