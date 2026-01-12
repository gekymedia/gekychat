<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This migration cleans up duplicate conversations created during testing.
     * It keeps the oldest conversation and deletes the newer duplicates.
     */
    public function up(): void
    {
        // Find duplicate direct conversations (non-group conversations with same two participants)
        // Use a simpler approach: find conversations with exactly 2 members and group by member pairs
        $duplicateGroups = DB::select("
            SELECT 
                GROUP_CONCAT(DISTINCT c.id ORDER BY c.created_at ASC) as conversation_ids,
                GROUP_CONCAT(DISTINCT cu1.user_id ORDER BY cu1.user_id ASC) as user_ids,
                MIN(c.created_at) as oldest_created_at
            FROM conversations c
            INNER JOIN conversation_user cu1 ON cu1.conversation_id = c.id
            INNER JOIN conversation_user cu2 ON cu2.conversation_id = c.id AND cu2.user_id != cu1.user_id
            WHERE c.is_group = 0
            GROUP BY cu1.user_id, cu2.user_id
            HAVING COUNT(DISTINCT c.id) > 1
        ");

        $deletedCount = 0;
        
        foreach ($duplicateGroups as $group) {
            $conversationIds = explode(',', $group->conversation_ids);
            $conversationIds = array_map('intval', $conversationIds);
            
            if (count($conversationIds) > 1) {
                // Keep the first (oldest) conversation, delete the rest
                $oldestConvId = array_shift($conversationIds);
                $toDelete = $conversationIds;

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
                            DB::table('message_attachments')
                                ->whereIn('message_id', $messageIds)
                                ->delete();
                            
                            // Delete message reactions
                            DB::table('reactions')
                                ->whereIn('message_id', $messageIds)
                                ->delete();
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
                    } catch (\Exception $e) {
                        DB::rollBack();
                        \Log::error("Failed to delete duplicate conversation {$convId}: " . $e->getMessage());
                    }
                }
            }
        }

        \Log::info("Cleaned up {$deletedCount} duplicate conversations");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse this operation as data has been deleted
    }
};
