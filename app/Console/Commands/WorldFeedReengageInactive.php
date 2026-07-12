<?php

namespace App\Console\Commands;

use App\Services\WorldFeedReengagementService;
use Illuminate\Console\Command;

class WorldFeedReengageInactive extends Command
{
    protected $signature = 'world-feed:reengage-inactive
                            {--hours=24 : Hours since last_seen_at before eligible}
                            {--limit=500 : Max users to process per run}
                            {--cooldown=48 : Minimum hours between sends to the same user}';

    protected $description = 'Send World feed suggestion pushes to users inactive for 24h+';

    public function handle(WorldFeedReengagementService $service): int
    {
        $hours = (int) $this->option('hours');
        $limit = (int) $this->option('limit');
        $cooldown = (int) $this->option('cooldown');

        $this->info("World re-engagement: inactive>={$hours}h limit={$limit} cooldown={$cooldown}h");

        $result = $service->run($hours, $limit, $cooldown);

        $this->info(sprintf(
            'Candidates=%d sent=%d skipped=%d',
            $result['candidates'],
            $result['sent'],
            $result['skipped']
        ));

        return self::SUCCESS;
    }
}
