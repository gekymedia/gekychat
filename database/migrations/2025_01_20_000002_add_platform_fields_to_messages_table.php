<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip if messages table doesn't exist yet (fresh migrations)
        if (!Schema::hasTable('messages')) {
            \Log::info("Skipping platform fields migration - messages table doesn't exist yet");
            return;
        }
        
        // Handle sender_id foreign key modification outside of Schema::table() to avoid constraint issues
        if (Schema::hasColumn('messages', 'sender_id')) {
            // Get the actual foreign key name from the database
            $foreignKeys = \DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'messages' 
                AND COLUMN_NAME = 'sender_id' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            
            // Drop foreign key if it exists using raw SQL
            if (!empty($foreignKeys)) {
                foreach ($foreignKeys as $fk) {
                    try {
                        \DB::statement("ALTER TABLE messages DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                    } catch (\Exception $e) {
                        // Continue if drop fails
                        \Log::warning('Failed to drop sender_id foreign key: ' . $e->getMessage());
                    }
                }
            }
            
            // Make column nullable using raw SQL (more reliable across DB drivers)
            try {
                \DB::statement('ALTER TABLE messages MODIFY sender_id BIGINT UNSIGNED NULL');
            } catch (\Exception $e) {
                \Log::warning('Failed to modify sender_id column: ' . $e->getMessage());
            }
            
            // Check if foreign key already exists before trying to add it
            // Only add if users table exists
            if (Schema::hasTable('users')) {
                $existingFk = \DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'messages' 
                    AND COLUMN_NAME = 'sender_id' 
                    AND REFERENCED_TABLE_NAME = 'users'
                    AND REFERENCED_COLUMN_NAME = 'id'
                ");
                
                // Re-add foreign key with nullOnDelete using raw SQL if it doesn't exist
                if (empty($existingFk)) {
                    try {
                        \DB::statement("
                            ALTER TABLE messages 
                            ADD CONSTRAINT messages_sender_id_foreign 
                            FOREIGN KEY (sender_id) 
                            REFERENCES users(id) 
                            ON DELETE SET NULL
                        ");
                    } catch (\Exception $e) {
                        // If foreign key creation fails, log but continue
                        \Log::warning('Failed to recreate sender_id foreign key: ' . $e->getMessage());
                    }
                }
            }
        }

        // Add other columns using Schema builder
        Schema::table('messages', function (Blueprint $table) {
            // Add sender_type to distinguish between user and platform messages
            if (!Schema::hasColumn('messages', 'sender_type')) {
                $table->string('sender_type')->nullable()->after('sender_id')->default('user');
            }

            // Add platform_client_id to track which API client sent the message
            if (!Schema::hasColumn('messages', 'platform_client_id')) {
                // Check if api_clients table exists first
                if (Schema::hasTable('api_clients')) {
                    $table->foreignId('platform_client_id')->nullable()->after('sender_type')
                        ->constrained('api_clients')->nullOnDelete();
                } else {
                    // If table doesn't exist yet, just add the column (migration will run later)
                    $table->unsignedBigInteger('platform_client_id')->nullable()->after('sender_type');
                }
            }

            // Add metadata JSON field for additional data
            if (!Schema::hasColumn('messages', 'metadata')) {
                $table->json('metadata')->nullable()->after('expires_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'metadata')) {
                $table->dropColumn('metadata');
            }
            if (Schema::hasColumn('messages', 'platform_client_id')) {
                try {
                    $table->dropForeign(['platform_client_id']);
                } catch (\Exception $e) {
                    // Continue if drop fails
                }
                $table->dropColumn('platform_client_id');
            }
            if (Schema::hasColumn('messages', 'sender_type')) {
                $table->dropColumn('sender_type');
            }
            // Note: We don't revert sender_id to non-nullable as it might break existing data
        });
    }
};
