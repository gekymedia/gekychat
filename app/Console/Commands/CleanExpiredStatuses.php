<?php

namespace App\Console\Commands;

use App\Models\OtpCode;
use App\Models\Status;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanExpiredStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statuses:clean-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired statuses (older than 24 hours)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Cleaning expired statuses...');

        // Get expired statuses
        $expiredStatuses = Status::where('expires_at', '<', now())->get();

        $count = 0;
        foreach ($expiredStatuses as $status) {
            // Delete media files
            if ($status->media_url) {
                Storage::disk('public')->delete($status->media_url);
            }
            if ($status->thumbnail_url) {
                Storage::disk('public')->delete($status->thumbnail_url);
            }

            // Delete status and related views
            $status->views()->delete();
            $status->forceDelete(); // Force delete to remove from database

            $count++;
        }

        $this->info("Deleted {$count} expired statuses.");

        // Also clean expired OTP codes
        $otpCount = OtpCode::cleanExpired();
        $this->info("Deleted {$otpCount} expired OTP codes.");

        return Command::SUCCESS;
    }
}

