<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Conversation;
use App\Models\User;

/**
 * Seeds public channels that all users can discover. These channels are
 * pre-created by the administrator account. To avoid duplication, they will
 * only be created if they don't already exist.
 */
class PublicChannelsSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure the admin user exists
        $admin = User::firstOrCreate(
            ['phone' => '0248229540'],
            [
                'name' => 'Emmanuel Gyabaa Yeboah',
                'password' => bcrypt('password'),
                'phone_verified_at' => now(),
                'is_admin' => true,
            ]
        );

        $channels = [
            'GekyChat' => [
                'description' => 'Official announcements and community chat for GekyChat.',
                'verified' => true,
            ],
            'Priority Admissions Office' => [
                'description' => 'Chat with the admissions team about your application.',
                'verified' => false,
            ],
            'Geky Media' => [
                'description' => 'Updates and announcements from Geky Media.',
                'verified' => false,
            ],
        ];

        foreach ($channels as $name => $meta) {
            // If a public group with this name exists, update its verified status and description
            $conversation = Conversation::where('is_group', true)
                ->where('name', $name)
                ->first();
            if ($conversation) {
                $conversation->update([
                    'description' => $meta['description'],
                    'verified'    => $meta['verified'] ?? false,
                    'is_private'  => false,
                ]);
            } else {
                $conversation = Conversation::create([
                    'is_group'   => true,
                    'name'       => $name,
                    'description'=> $meta['description'],
                    'created_by' => $admin->id,
                    'is_private' => false,
                    'verified'   => $meta['verified'] ?? false,
                ]);
                // Add creator as owner
                $conversation->members()->attach($admin->id, ['role' => 'owner']);
            }
        }
    }
}