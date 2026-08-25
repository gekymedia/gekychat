<?php

namespace App\Console\Commands;

use App\Models\OtpCode;
use App\Models\Status;
use App\Services\VideoUploadLimitService;
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
     * @var string
     */
    protected $description = 'Delete expired statuses after a short grace period (files + rows)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $graceHours = VideoUploadLimitService::STATUS_PURGE_HOURS_AFTER_EXPIRY;
        $this->info("Cleaning statuses expired more than {$graceHours} hours ago...");

        // Visible for 24h (expires_at). Files stay a bit longer so chat
        // replies / clock skew still resolve, then purge.
        $purgeBefore = now()->subHours($graceHours);
        $expiredStatuses = Status::where('expires_at', '<', $purgeBefore)->get();

        $count = 0;
        foreach ($expiredStatuses as $status) {
            $this->deleteStoredPath($status->getRawOriginal('media_url'));
            $this->deleteStoredPath($status->getRawOriginal('thumbnail_url'));

            $status->views()->delete();
            $status->forceDelete();

            $count++;
        }

        $this->info("Deleted {$count} expired statuses.");

        $otpCount = OtpCode::cleanExpired();
        $this->info("Deleted {$otpCount} expired OTP codes.");

        return Command::SUCCESS;
    }

    /**
     * Status accessors return full HTTPS URLs; Storage needs the relative path.
     */
    private function deleteStoredPath(mixed $raw): void
    {
        if (! is_string($raw) || $raw === '') {
            return;
        }

        $path = $raw;
        if (str_starts_with($path, 'http')) {
            if (preg_match('#/storage/(.+)$#', $path, $m)) {
                $path = $m[1];
            } else {
                return;
            }
        }

        $path = ltrim($path, '/');
        if ($path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
