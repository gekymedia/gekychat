<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWorldFeedVideoCompress;
use App\Models\WorldFeedPost;
use Illuminate\Console\Command;

/**
 * Queue compress for existing World Feed videos that are not yet ready.
 */
class BackfillWorldFeedVideoCompress extends Command
{
    protected $signature = 'world-feed:backfill-compress
                            {--limit=50 : Max posts to queue}
                            {--force : Re-queue even if status is ready}';

    protected $description = 'Dispatch Phase 1 compress jobs for World Feed videos';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');

        $query = WorldFeedPost::query()
            ->where('type', 'video')
            ->whereNotNull('media_url')
            ->orderByDesc('id');

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('video_processing_status')
                    ->orWhereNotIn('video_processing_status', ['ready', 'processing']);
            });
        }

        $ids = $query->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            ProcessWorldFeedVideoCompress::dispatch((int) $id);
            $this->line("Queued post #{$id}");
        }

        $this->info('Queued '.$ids->count().' compress job(s).');

        return self::SUCCESS;
    }
}
