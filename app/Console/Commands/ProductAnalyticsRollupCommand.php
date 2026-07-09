<?php

namespace App\Console\Commands;

use App\Services\ProductAnalyticsIngestService;
use App\Services\ProductAnalyticsReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProductAnalyticsRollupCommand extends Command
{
    protected $signature = 'analytics:rollup {--date= : YYYY-MM-DD date to rollup (default yesterday)}';

    protected $description = 'Close stale analytics sessions and build daily rollups';

    public function handle(
        ProductAnalyticsIngestService $ingest,
        ProductAnalyticsReportService $reports,
    ): int {
        $closed = $ingest->closeStaleSessions(30);
        $this->info("Closed {$closed} stale sessions.");

        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $reports->buildDailyRollups($date);
        $this->info('Rollups built for '.$date->toDateString());

        return self::SUCCESS;
    }
}
