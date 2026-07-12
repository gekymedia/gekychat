<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Clean expired statuses every hour
Schedule::command('statuses:clean-expired')->hourly();

// Birthday bot DMs replaced by the Telegram-style sidebar banner (mutual contacts).
// Schedule::job(new \App\Jobs\SendBirthdayReminders)->dailyAt('08:00');

// Fetch emails from IMAP every 5 minutes
Schedule::command('email:fetch --limit=50')->everyFiveMinutes();

// Product analytics: close stale sessions + daily rollups
Schedule::command('analytics:rollup')->dailyAt('01:15');

// ==================== NEW: DATABASE IMPROVEMENTS SCHEDULED TASKS ====================

// Cleanup expired typing indicators every minute
Schedule::command('cleanup:typing-indicators')->everyMinute();

// Process scheduled messages every minute
Schedule::command('process:scheduled-messages')->everyMinute();

// End stale call sessions (unanswered ring, expired links, empty LiveKit rooms)
Schedule::command('calls:cleanup-stale')->everyMinute();

// World feed re-engagement: suggest a post to users inactive ~24h+
Schedule::command('world-feed:reengage-inactive')->hourly();

// Cleanup old audit logs every week (keep last 90 days)
Schedule::command('cleanup:audit-logs --days=90')->weekly();
