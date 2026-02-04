<?php

namespace App\Console\Commands;

use App\Models\TestingMode;
use App\Models\User;
use Illuminate\Console\Command;

class TestingModeEnable extends Command
{
    protected $signature = 'testing-mode:enable {phone?} {--disable}';
    protected $description = 'Enable testing mode and add user to allowlist';

    public function handle()
    {
        $phone = $this->argument('phone');
        $disable = $this->option('disable');

        // Get or create testing mode record
        $testingMode = TestingMode::firstOrCreate([]);

        if ($disable) {
            $testingMode->is_enabled = false;
            $testingMode->save();
            $this->info('✅ Testing mode DISABLED');
            return 0;
        }

        // Enable testing mode
        $testingMode->is_enabled = true;
        
        // Add user to allowlist if phone provided
        if ($phone) {
            $user = User::where('phone', $phone)->first();
            
            if (!$user) {
                $this->error("❌ User not found with phone: {$phone}");
                return 1;
            }

            $userIds = $testingMode->user_ids ?? [];
            
            if (!in_array($user->id, $userIds)) {
                $userIds[] = $user->id;
                $testingMode->user_ids = $userIds;
                $this->info("✅ Added user {$user->name} (ID: {$user->id}) to testing mode allowlist");
            } else {
                $this->info("ℹ️  User {$user->name} (ID: {$user->id}) is already in testing mode allowlist");
            }
        }

        $testingMode->save();
        $this->info('✅ Testing mode ENABLED');
        
        if ($testingMode->user_ids) {
            $this->info('📋 Allowlisted user IDs: ' . implode(', ', $testingMode->user_ids));
            
            // Show user details
            $users = User::whereIn('id', $testingMode->user_ids)->get();
            $this->table(
                ['ID', 'Name', 'Phone'],
                $users->map(fn($u) => [$u->id, $u->name, $u->phone])
            );
        }

        return 0;
    }
}
