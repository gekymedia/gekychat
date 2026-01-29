<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds critical missing indexes identified in database performance analysis:
     * 1. Attachments polymorphic relationship (CRITICAL)
     * 2. Device tokens active lookup for FCM (HIGH)
     * 3. Group members role-based queries (MEDIUM)
     * 4. Attachments compression queue (MEDIUM)
     */
    public function up(): void
    {
        // CRITICAL: Attachments polymorphic index
        // Every message with media queries this - missing index causes full table scans
        if (!$this->indexExists('attachments', 'idx_attachments_attachable')) {
            DB::statement('CREATE INDEX idx_attachments_attachable ON attachments(attachable_type, attachable_id)');
            echo "✅ Created index: idx_attachments_attachable\n";
        }
        
        // HIGH: Device tokens active lookup
        // Used for every push notification sent
        if (!$this->indexExists('device_tokens', 'idx_device_tokens_user_active')) {
            // Check if is_active column exists first
            if (Schema::hasColumn('device_tokens', 'is_active')) {
                DB::statement('CREATE INDEX idx_device_tokens_user_active ON device_tokens(user_id, is_active, updated_at)');
                echo "✅ Created index: idx_device_tokens_user_active\n";
            } else {
                echo "⚠️ Skipped idx_device_tokens_user_active - is_active column doesn't exist\n";
            }
        }
        
        // MEDIUM: Group members role checks
        // Improves admin checks and permission queries
        if (!$this->indexExists('group_members', 'idx_group_members_role')) {
            DB::statement('CREATE INDEX idx_group_members_role ON group_members(group_id, user_id, role)');
            echo "✅ Created index: idx_group_members_role\n";
        }
        
        // MEDIUM: Group members user role lookup
        // Improves "get user's groups by role" queries
        if (!$this->indexExists('group_members', 'idx_group_members_user_role')) {
            // Check if joined_at column exists
            if (Schema::hasColumn('group_members', 'joined_at')) {
                DB::statement('CREATE INDEX idx_group_members_user_role ON group_members(user_id, role, joined_at)');
                echo "✅ Created index: idx_group_members_user_role\n";
            } else {
                // Fallback without joined_at
                DB::statement('CREATE INDEX idx_group_members_user_role ON group_members(user_id, role)');
                echo "✅ Created index: idx_group_members_user_role (without joined_at)\n";
            }
        }
        
        // MEDIUM: Attachments compression queue
        // Helps background compression jobs find pending media
        if (!$this->indexExists('attachments', 'idx_attachments_compression')) {
            if (Schema::hasColumn('attachments', 'compression_status')) {
                DB::statement('CREATE INDEX idx_attachments_compression ON attachments(compression_status, created_at)');
                echo "✅ Created index: idx_attachments_compression\n";
            } else {
                echo "⚠️ Skipped idx_attachments_compression - compression_status column doesn't exist\n";
            }
        }
        
        // OPTIONAL: Device tokens platform filtering
        // Useful for platform-specific queries
        if (!$this->indexExists('device_tokens', 'idx_device_tokens_platform')) {
            $hasIsActive = Schema::hasColumn('device_tokens', 'is_active');
            if ($hasIsActive) {
                DB::statement('CREATE INDEX idx_device_tokens_platform ON device_tokens(user_id, platform, is_active)');
                echo "✅ Created index: idx_device_tokens_platform\n";
            } else {
                DB::statement('CREATE INDEX idx_device_tokens_platform ON device_tokens(user_id, platform)');
                echo "✅ Created index: idx_device_tokens_platform (without is_active)\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes in reverse order
        $this->dropIndexIfExists('device_tokens', 'idx_device_tokens_platform');
        $this->dropIndexIfExists('attachments', 'idx_attachments_compression');
        $this->dropIndexIfExists('group_members', 'idx_group_members_user_role');
        $this->dropIndexIfExists('group_members', 'idx_group_members_role');
        $this->dropIndexIfExists('device_tokens', 'idx_device_tokens_user_active');
        $this->dropIndexIfExists('attachments', 'idx_attachments_attachable');
    }
    
    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $connection = Schema::getConnection();
            $database = $connection->getDatabaseName();
            
            $result = $connection->select(
                "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                [$database, $table, $index]
            );
            
            return $result[0]->count > 0;
        } catch (\Exception $e) {
            // If check fails, assume index doesn't exist
            return false;
        }
    }
    
    /**
     * Drop an index if it exists
     */
    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            try {
                DB::statement("ALTER TABLE {$table} DROP INDEX {$index}");
                echo "✅ Dropped index: {$index}\n";
            } catch (\Exception $e) {
                echo "⚠️ Failed to drop index {$index}: {$e->getMessage()}\n";
            }
        }
    }
};
