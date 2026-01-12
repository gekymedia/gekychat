<?php

namespace App\Console\Commands;

use App\Services\ImapEmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:fetch 
                            {--limit=200 : Maximum number of emails to fetch}
                            {--mark-seen : Mark emails as seen after processing}
                            {--folder= : IMAP folder to fetch from (default: INBOX)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch emails from IMAP server and process them as chat messages';

    /**
     * Execute the console command.
     */
    public function handle(ImapEmailService $imapService): int
    {
        $this->info('Connecting to IMAP server...');

        $connection = $imapService->connect();
        
        if (!$connection) {
            $this->error('Failed to connect to IMAP server. Check your configuration.');
            return Command::FAILURE;
        }

        $this->info('Connected successfully. Fetching emails...');

        $limit = (int) $this->option('limit');
        $markSeen = $this->option('mark-seen');

        $result = $imapService->fetchEmails($limit, $markSeen);

        $this->info("Processed {$result['processed']} emails.");

        if (!empty($result['errors'])) {
            $this->warn('Encountered ' . count($result['errors']) . ' errors during processing.');
            foreach ($result['errors'] as $error) {
                $this->error('Error: ' . ($error['error'] ?? 'Unknown error'));
            }
        }

        return Command::SUCCESS;
    }
}
