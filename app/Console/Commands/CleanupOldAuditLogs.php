<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class CleanupOldAuditLogs extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cleanup:audit-logs {--days=90 : Number of days to keep}';

    /**
     * The console command description.
     */
    protected $description = 'Delete audit logs older than specified days (default: 90 days)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if audit_logs table exists
        if (!\Schema::hasTable('audit_logs')) {
            $this->info('Audit logs table does not exist yet. Skipping cleanup.');
            return 0;
        }
        
        $days = (int) $this->option('days');
        
        if ($days < 1) {
            $this->error('Days must be at least 1');
            return 1;
        }
        
        $cutoffDate = now()->subDays($days);
        
        $deleted = AuditLog::where('created_at', '<', $cutoffDate)->delete();
        
        $this->info("✅ Deleted {$deleted} audit logs older than {$days} days (before {$cutoffDate->toDateString()})");
        
        // Show remaining logs count
        $remaining = AuditLog::count();
        $this->info("📊 Remaining audit logs: {$remaining}");
        
        return 0;
    }
}
