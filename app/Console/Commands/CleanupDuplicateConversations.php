<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class CleanupDuplicateConversations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conversations:cleanup-duplicates 
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up duplicate conversations created during testing. Keeps the oldest conversation and deletes newer duplicates.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No data will be deleted');
        }

        $this->info('Searching for duplicate conversations...');

        // Find duplicate direct conversations (non-group conversations with same two participants)
        $duplicateGroups = DB::select("
            SELECT 
                GROUP_CONCAT(DISTINCT c.id ORDER BY c.created_at ASC) as conversation_ids,
                GROUP_CONCAT(DISTINCT cu1.user_id ORDER BY cu1.user_id ASC) as user_ids,
                MIN(c.created_at) as oldest_created_at,
                COUNT(DISTINCT c.id) as duplicate_count
            FROM conversations c
            INNER JOIN conversation_user cu1 ON cu1.conversation_id = c.id
            INNER JOIN conversation_user cu2 ON cu2.conversation_id = c.id AND cu2.user_id != cu1.user_id
            WHERE c.is_group = 0
            GROUP BY cu1.user_id, cu2.user_id
            HAVING COUNT(DISTINCT c.id) > 1
        ");

        if (empty($duplicateGroups)) {
            $this->info('✅ No duplicate conversations found.');
            return 0;
        }

        $this->info("Found " . count($duplicateGroups) . " groups of duplicate conversations.");

        $totalToDelete = 0;
        $deletedCount = 0;
        
        foreach ($duplicateGroups as $group) {
            $conversationIds = array_map('intval', explode(',', $group->conversation_ids));
            $duplicateCount = (int)$group->duplicate_count;
            
            if (count($conversationIds) > 1) {
                // Keep the first (oldest) conversation, delete the rest
                $oldestConvId = array_shift($conversationIds);
                $toDelete = $conversationIds;
                $totalToDelete += count($toDelete);

                $this->line("  User pair: {$group->user_ids}");
                $this->line("  Keeping conversation ID: {$oldestConvId} (oldest)");
                $this->line("  Will delete " . count($toDelete) . " duplicate(s): " . implode(', ', $toDelete));

                if ($isDryRun) {
                    continue;
                }

                foreach ($toDelete as $convId) {
                    try {
                        DB::beginTransaction();

                        // Get all message IDs for this conversation
                        $messageIds = DB::table('messages')
                            ->where('conversation_id', $convId)
                            ->pluck('id')
                            ->toArray();
                        
                        // Delete message statuses
                        if (!empty($messageIds)) {
                            DB::table('message_statuses')
                                ->whereIn('message_id', $messageIds)
                                ->delete();
                            
                            // Delete message attachments
                            if (Schema::hasTable('message_attachments')) {
                                DB::table('message_attachments')
                                    ->whereIn('message_id', $messageIds)
                                    ->delete();
                            }
                            
                            // Delete message reactions
                            if (Schema::hasTable('reactions')) {
                                DB::table('reactions')
                                    ->whereIn('message_id', $messageIds)
                                    ->delete();
                            }
                        }

                        // Delete messages
                        DB::table('messages')
                            ->where('conversation_id', $convId)
                            ->delete();

                        // Delete conversation_user pivot entries
                        DB::table('conversation_user')
                            ->where('conversation_id', $convId)
                            ->delete();

                        // Delete conversation labels (if table exists)
                        if (Schema::hasTable('conversation_label')) {
                            DB::table('conversation_label')
                                ->where('conversation_id', $convId)
                                ->delete();
                        }

                        // Finally, delete the conversation
                        DB::table('conversations')
                            ->where('id', $convId)
                            ->delete();

                        DB::commit();
                        $deletedCount++;
                        $this->info("  ✅ Deleted conversation ID: {$convId}");
                    } catch (\Exception $e) {
                        DB::rollBack();
                        $this->error("  ❌ Failed to delete conversation {$convId}: " . $e->getMessage());
                        Log::error("Failed to delete duplicate conversation {$convId}: " . $e->getMessage());
                    }
                }
            }
        }

        if ($isDryRun) {
            $this->info("\n📊 Summary:");
            $this->info("  Total duplicate conversations that would be deleted: {$totalToDelete}");
            $this->info("\nRun without --dry-run to actually delete them.");
        } else {
            $this->info("\n✅ Cleanup complete!");
            $this->info("  Deleted {$deletedCount} duplicate conversation(s)");
            Log::info("Cleaned up {$deletedCount} duplicate conversations");
        }

        return 0;
    }
}
