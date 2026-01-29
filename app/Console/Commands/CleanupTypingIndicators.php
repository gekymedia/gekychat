<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupTypingIndicators extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cleanup:typing-indicators';

    /**
     * The console command description.
     */
    protected $description = 'Remove expired typing indicators from database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if typing_indicators table exists
        if (!DB::getSchemaBuilder()->hasTable('typing_indicators')) {
            $this->info('Typing indicators table does not exist yet. Skipping cleanup.');
            return 0;
        }
        
        $deleted = DB::table('typing_indicators')
            ->where('expires_at', '<', now())
            ->delete();
        
        $this->info("✅ Cleaned up {$deleted} expired typing indicators");
        
        return 0;
    }
}
