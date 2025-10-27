<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. Add deleted_at column if needed
        if (Schema::hasTable('group_message_statuses') && !Schema::hasColumn('group_message_statuses', 'deleted_at')) {
            Schema::table('group_message_statuses', function (Blueprint $table) {
                $table->timestamp('deleted_at')->nullable()->after('status');
            });
        }

        // 2. Try to add indexes safely (they'll fail gracefully if they exist)
        $this->safeAddIndex('group_message_statuses', ['user_id', 'status']);
        $this->safeAddIndex('group_message_statuses', ['group_message_id', 'user_id']);

        // 3. Ensure all existing group messages have status entries for their senders
        $this->ensureMessageStatuses();
    }

    public function down()
    {
        // Safe rollback - only remove columns we added
        if (Schema::hasTable('group_message_statuses') && Schema::hasColumn('group_message_statuses', 'deleted_at')) {
            Schema::table('group_message_statuses', function (Blueprint $table) {
                $table->dropColumn('deleted_at');
            });
        }
    }

    /**
     * Safely add index (will catch exception if index already exists)
     */
    private function safeAddIndex(string $table, array $columns)
    {
        try {
            Schema::table($table, function (Blueprint $table) use ($columns) {
                $table->index($columns);
            });
            \Log::info("Index created successfully for columns: " . implode(', ', $columns));
        } catch (\Exception $e) {
            // Index already exists, that's fine
            \Log::info("Index already exists for columns: " . implode(', ', $columns));
        }
    }

    /**
     * Ensure all existing group messages have status entries
     */
    private function ensureMessageStatuses()
    {
        try {
            // Check if both tables exist
            if (!Schema::hasTable('group_messages') || !Schema::hasTable('group_message_statuses')) {
                \Log::info('Required tables do not exist, skipping status creation');
                return;
            }

            // Use raw SQL to find missing statuses
            $missingStatuses = DB::select("
                SELECT gm.id, gm.sender_id, gm.created_at 
                FROM group_messages gm
                LEFT JOIN group_message_statuses gms ON gm.id = gms.group_message_id AND gm.sender_id = gms.user_id
                WHERE gms.id IS NULL
            ");

            $created = 0;
            foreach ($missingStatuses as $message) {
                try {
                    DB::table('group_message_statuses')->insert([
                        'group_message_id' => $message->id,
                        'user_id' => $message->sender_id,
                        'status' => 'sent',
                        'created_at' => $message->created_at ?? now(),
                        'updated_at' => now(),
                    ]);
                    $created++;
                } catch (\Exception $e) {
                    // Skip duplicate entries
                    \Log::warning('Could not create status for message ' . $message->id . ': ' . $e->getMessage());
                }
            }

            \Log::info("Migration: Created {$created} missing group message status entries");
            
        } catch (\Exception $e) {
            \Log::error('Migration error ensuring group message statuses: ' . $e->getMessage());
        }
    }
};