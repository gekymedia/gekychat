<?php

namespace App\Console\Commands;

use App\Models\TestingMode;
use App\Models\User;
use Illuminate\Console\Command;

class TestingModeRemove extends Command
{
    protected $signature = 'testing-mode:remove {phone}';
    protected $description = 'Remove user from testing mode allowlist';

    public function handle()
    {
        $phone = $this->argument('phone');
        $testingMode = TestingMode::first();

        if (!$testingMode) {
            $this->error('❌ No testing mode configuration found');
            return 1;
        }

        $user = User::where('phone', $phone)->first();
        
        if (!$user) {
            $this->error("❌ User not found with phone: {$phone}");
            return 1;
        }

        $userIds = $testingMode->user_ids ?? [];
        
        if (in_array($user->id, $userIds)) {
            $userIds = array_values(array_diff($userIds, [$user->id]));
            $testingMode->user_ids = $userIds;
            $testingMode->save();
            
            $this->info("✅ Removed user {$user->name} (ID: {$user->id}) from testing mode allowlist");
            
            if (empty($userIds)) {
                $this->warn('⚠️  Testing mode allowlist is now empty');
            }
        } else {
            $this->info("ℹ️  User {$user->name} (ID: {$user->id}) was not in testing mode allowlist");
        }

        return 0;
    }
}
