<?php

namespace App\Console\Commands;

use App\Services\CallSessionStaleCleanupService;
use Illuminate\Console\Command;

class CleanupStaleCallSessions extends Command
{
    protected $signature = 'calls:cleanup-stale';

    protected $description = 'End stale call sessions (unanswered ring, expired links, empty LiveKit rooms) and write call logs';

    public function handle(CallSessionStaleCleanupService $cleanup): int
    {
        $result = $cleanup->cleanup();

        $this->info(sprintf(
            'Ended %d stale call session(s): timeout=%d, expired=%d, stale=%d',
            $result['ended'],
            $result['reasons']['timeout'] ?? 0,
            $result['reasons']['expired'] ?? 0,
            $result['reasons']['stale'] ?? 0,
        ));

        return 0;
    }
}
