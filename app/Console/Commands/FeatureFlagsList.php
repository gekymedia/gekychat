<?php

namespace App\Console\Commands;

use App\Models\FeatureFlag;
use Illuminate\Console\Command;

class FeatureFlagsList extends Command
{
    protected $signature = 'feature-flags:list';
    protected $description = 'List all feature flags';

    public function handle()
    {
        $flags = FeatureFlag::all();

        if ($flags->isEmpty()) {
            $this->warn('⚠️  No feature flags found');
            $this->info('💡 Create flags in the database or use a seeder');
            return 0;
        }

        $this->info('═══════════════════════════════════════');
        $this->info('  FEATURE FLAGS');
        $this->info('═══════════════════════════════════════');
        $this->newLine();

        $this->table(
            ['ID', 'Key', 'Status', 'Platform', 'Description'],
            $flags->map(fn($f) => [
                $f->id,
                $f->key,
                $f->enabled ? '✅ ENABLED' : '❌ DISABLED',
                $f->platform ?? 'all',
                $f->description ?? 'N/A'
            ])
        );

        return 0;
    }
}
