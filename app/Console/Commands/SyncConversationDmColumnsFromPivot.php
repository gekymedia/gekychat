<?php

namespace App\Console\Commands;

use App\Services\ConversationService;
use Illuminate\Console\Command;

/**
 * Repair conversations.user_one_id / user_two_id from conversation_user (source of truth).
 * Safe to run multiple times (idempotent).
 */
class SyncConversationDmColumnsFromPivot extends Command
{
    protected $signature = 'conversations:sync-dm-columns-from-pivot
                            {--limit= : Max rows to process (for testing)}';

    protected $description = 'Sync user_one_id/user_two_id from conversation_user pivot for all conversations';

    public function handle(ConversationService $conversationService): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $this->info('Syncing denormalized DM columns from pivot...');

        $result = $conversationService->syncAllDenormalizedColumnsFromPivot($limit);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $result['processed']],
                ['Changed', $result['changed']],
                ['Errors', $result['errors']],
            ]
        );

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
