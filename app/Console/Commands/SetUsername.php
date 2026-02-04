<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetUsername extends Command
{
    protected $signature = 'user:set-username {phone} {username}';
    protected $description = 'Set username for a user by phone number';

    public function handle()
    {
        $phone = $this->argument('phone');
        $username = $this->argument('username');

        $user = User::where('phone', $phone)->first();

        if (!$user) {
            $this->error("❌ User with phone {$phone} not found");
            return 1;
        }

        // Check if username is already taken
        $existing = User::where('username', $username)->where('id', '!=', $user->id)->first();
        if ($existing) {
            $this->error("❌ Username '{$username}' is already taken");
            return 1;
        }

        $user->username = $username;
        $user->save();

        $this->info("✅ Username set successfully");
        $this->info("   User: {$user->name}");
        $this->info("   Phone: {$user->phone}");
        $this->info("   Username: {$username}");

        return 0;
    }
}
