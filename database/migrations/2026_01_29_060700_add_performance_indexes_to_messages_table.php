<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Performance optimization for message queries
     */
    public function up(): void
    {
        // Use raw SQL to check and create indexes safely
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        
        // Check and create composite index for conversation_id + id
        $result = $connection->select("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE table_schema = ? 
            AND table_name = 'messages' 
            AND index_name = 'idx_conversation_id_id'
        ", [$database]);
        
        if ($result[0]->count == 0) {
            $connection->statement("
                ALTER TABLE messages 
                ADD INDEX idx_conversation_id_id (conversation_id, id)
            ");
        }
        
        // Check and create composite index for conversation_id + created_at + id
        $result = $connection->select("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE table_schema = ? 
            AND table_name = 'messages' 
            AND index_name = 'idx_conversation_created_id'
        ", [$database]);
        
        if ($result[0]->count == 0) {
            $connection->statement("
                ALTER TABLE messages 
                ADD INDEX idx_conversation_created_id (conversation_id, created_at, id)
            ");
        }
        
        // Check and create index for sender_id
        $result = $connection->select("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE table_schema = ? 
            AND table_name = 'messages' 
            AND index_name = 'idx_sender_id'
        ", [$database]);
        
        if ($result[0]->count == 0 && Schema::hasColumn('messages', 'sender_id')) {
            $connection->statement("
                ALTER TABLE messages 
                ADD INDEX idx_sender_id (sender_id)
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Drop indexes if they exist (Laravel will ignore if they don't)
            try {
                $table->dropIndex('idx_conversation_id_id');
            } catch (\Exception $e) {
                // Index doesn't exist, ignore
            }
            
            try {
                $table->dropIndex('idx_conversation_created_id');
            } catch (\Exception $e) {
                // Index doesn't exist, ignore
            }
            
            try {
                $table->dropIndex('idx_sender_id');
            } catch (\Exception $e) {
                // Index doesn't exist, ignore
            }
        });
    }
};
