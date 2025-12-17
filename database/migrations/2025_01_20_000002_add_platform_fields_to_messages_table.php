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
        Schema::table('messages', function (Blueprint $table) {
            // Make sender_id nullable for platform messages
            // Note: This requires the foreign key to be dropped first
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
                
                // Drop foreign key if it exists
                if (!empty($foreignKeys)) {
                    foreach ($foreignKeys as $fk) {
                        try {
                            $table->dropForeign($fk->CONSTRAINT_NAME);
                        } catch (\Exception $e) {
                            // Continue if drop fails
                        }
                    }
                }
                
                // Make column nullable using raw SQL (more reliable across DB drivers)
                \DB::statement('ALTER TABLE messages MODIFY sender_id BIGINT UNSIGNED NULL');
                
                // Re-add foreign key with nullOnDelete
                try {
                    $table->foreign('sender_id')->references('id')->on('users')->nullOnDelete();
                } catch (\Exception $e) {
                    // If foreign key creation fails, log but continue
                    \Log::warning('Failed to recreate sender_id foreign key: ' . $e->getMessage());
                }
            }

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
