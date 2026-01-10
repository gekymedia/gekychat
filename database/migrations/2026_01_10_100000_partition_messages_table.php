<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Partitions the messages table by month for better performance
     * on large datasets (millions of messages)
     */
    public function up(): void
    {
        // Check if table has data
        $hasData = DB::table('messages')->exists();
        
        if ($hasData) {
            echo "WARNING: Messages table has data. Partitioning requires manual intervention.\n";
            echo "Please backup your data and run this migration on a maintenance window.\n";
            return;
        }
        
        // Drop existing table and recreate with partitioning
        DB::statement('DROP TABLE IF EXISTS messages_backup');
        DB::statement('RENAME TABLE messages TO messages_backup');
        
        // Create partitioned table
        DB::statement("
            CREATE TABLE messages (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                conversation_id BIGINT UNSIGNED NOT NULL,
                sender_id BIGINT UNSIGNED NOT NULL,
                body TEXT,
                type VARCHAR(50) DEFAULT 'text',
                reply_to_id BIGINT UNSIGNED NULL,
                deleted_at TIMESTAMP NULL,
                deleted_for_everyone BOOLEAN DEFAULT FALSE,
                edited_at TIMESTAMP NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id, created_at),
                KEY idx_conversation_sender (conversation_id, sender_id, created_at),
                KEY idx_sender (sender_id),
                KEY idx_reply_to (reply_to_id)
            ) ENGINE=InnoDB
            PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at)) (
                PARTITION p202601 VALUES LESS THAN (202602),
                PARTITION p202602 VALUES LESS THAN (202603),
                PARTITION p202603 VALUES LESS THAN (202604),
                PARTITION p202604 VALUES LESS THAN (202605),
                PARTITION p202605 VALUES LESS THAN (202606),
                PARTITION p202606 VALUES LESS THAN (202607),
                PARTITION p202607 VALUES LESS THAN (202608),
                PARTITION p202608 VALUES LESS THAN (202609),
                PARTITION p202609 VALUES LESS THAN (202610),
                PARTITION p202610 VALUES LESS THAN (202611),
                PARTITION p202611 VALUES LESS THAN (202612),
                PARTITION p202612 VALUES LESS THAN (202701),
                PARTITION p_future VALUES LESS THAN MAXVALUE
            )
        ");
        
        // Copy data back if backup exists
        $backupExists = DB::select("SHOW TABLES LIKE 'messages_backup'");
        if (!empty($backupExists)) {
            DB::statement('INSERT INTO messages SELECT * FROM messages_backup');
            echo "Data migrated from backup.\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore from backup if exists
        $backupExists = DB::select("SHOW TABLES LIKE 'messages_backup'");
        
        if (!empty($backupExists)) {
            DB::statement('DROP TABLE IF EXISTS messages');
            DB::statement('RENAME TABLE messages_backup TO messages');
        }
    }
};
