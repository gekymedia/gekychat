<?php

namespace App\Console\Commands;

use App\Models\TestingMode;
use App\Models\User;
use App\Models\FeatureFlag;
use Illuminate\Console\Command;

class TestingModeStatus extends Command
{
    protected $signature = 'testing-mode:status';
    protected $description = 'Show testing mode status and configuration';

    public function handle()
    {
        $testingMode = TestingMode::first();

        if (!$testingMode) {
            $this->warn('⚠️  No testing mode configuration found');
            $this->info('💡 Run: php artisan testing-mode:enable [phone] to set up');
            return 0;
        }

        $this->info('═══════════════════════════════════════');
        $this->info('  TESTING MODE STATUS');
        $this->info('═══════════════════════════════════════');
        $this->newLine();

        $status = $testingMode->is_enabled ? '✅ ENABLED' : '❌ DISABLED';
        $this->line("Status: {$status}");
        $this->newLine();

        if ($testingMode->user_ids && count($testingMode->user_ids) > 0) {
            $this->info('📋 Allowlisted Users:');
            $users = User::whereIn('id', $testingMode->user_ids)->get();
            
            if ($users->isEmpty()) {
                $this->warn('   No users found with IDs: ' . implode(', ', $testingMode->user_ids));
            } else {
                $this->table(
                    ['ID', 'Name', 'Phone'],
                    $users->map(fn($u) => [$u->id, $u->name ?? 'N/A', $u->phone])
                );
            }
        } else {
            $this->warn('⚠️  No users in allowlist');
            $this->info('💡 Add users: php artisan testing-mode:enable [phone]');
        }

        $this->newLine();
        $this->info('🚩 Feature Flags:');
        $flags = FeatureFlag::where('enabled', true)->get();
        
        if ($flags->isEmpty()) {
            $this->warn('   No feature flags enabled');
        } else {
            $this->table(
                ['Key', 'Platform', 'Description'],
                $flags->map(fn($f) => [$f->key, $f->platform ?? 'all', $f->description ?? 'N/A'])
            );
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info('💡 Commands:');
        $this->line('  Enable:  php artisan testing-mode:enable [phone]');
        $this->line('  Disable: php artisan testing-mode:enable --disable');
        $this->line('  Remove:  php artisan testing-mode:remove [phone]');
        $this->info('═══════════════════════════════════════');

        return 0;
    }
}
