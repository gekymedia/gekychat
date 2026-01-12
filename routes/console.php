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

// Send birthday reminders daily at 8:00 AM
Schedule::job(new \App\Jobs\SendBirthdayReminders)->dailyAt('08:00');
