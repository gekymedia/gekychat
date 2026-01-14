<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GekySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@gekychat.com'],
            [
                'name' => 'Geky Admin',
                'email' => 'admin@gekychat.com',
                'phone' => '0240000000',
                'password' => Hash::make('Gyabaa2000;'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        // Ensure admin flag is set
        $user->is_admin = true;
        $user->save();

        $this->command->info('Geky admin user created/updated successfully!');
        $this->command->info('Email: admin@gekychat.com');
        $this->command->info('Password: Gyabaa2000;');
        $this->command->info('Admin: true');
    }
}
