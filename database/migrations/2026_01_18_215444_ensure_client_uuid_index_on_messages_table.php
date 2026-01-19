<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds index on client_uuid for efficient deduplication lookups
     * when syncing offline messages.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Check if client_uuid column exists, if not add it
            if (!Schema::hasColumn('messages', 'client_uuid')) {
                $table->string('client_uuid', 100)->nullable()->after('id');
            }
        });
        
        // Add composite index for efficient deduplication queries
        // This index helps with: WHERE conversation_id = X AND sender_id = Y AND client_uuid = Z
        // Use raw SQL to check if index exists first
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();
        
        if ($driver === 'mysql') {
            $databaseName = $connection->getDatabaseName();
            $indexExists = DB::select(
                "SELECT COUNT(*) as count FROM information_schema.statistics 
                 WHERE table_schema = ? AND table_name = 'messages' AND index_name = 'idx_messages_client_uuid'",
                [$databaseName]
            );
            
            if ($indexExists[0]->count == 0) {
                DB::statement('CREATE INDEX idx_messages_client_uuid ON messages(conversation_id, sender_id, client_uuid)');
            }
        } elseif ($driver === 'sqlite') {
            // SQLite - try to create index (will fail silently if exists)
            try {
                DB::statement('CREATE INDEX IF NOT EXISTS idx_messages_client_uuid ON messages(conversation_id, sender_id, client_uuid)');
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_client_uuid');
            // Note: We don't drop the client_uuid column as it may contain data
        });
    }
};
