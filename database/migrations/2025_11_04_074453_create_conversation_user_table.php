<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Check if table exists first
        if (!Schema::hasTable('conversation_user')) {
            Schema::create('conversation_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('role')->default('member');
                $table->foreignId('last_read_message_id')->nullable()->constrained('messages')->onDelete('set null');
                $table->timestamp('muted_until')->nullable();
                $table->timestamp('pinned_at')->nullable();
                $table->timestamps();

                $table->unique(['conversation_id', 'user_id']);
            });
        } else {
            // Table exists, check and add missing columns
            
            // Check and add 'role' column
            if (!Schema::hasColumn('conversation_user', 'role')) {
                Schema::table('conversation_user', function (Blueprint $table) {
                    $table->string('role')->default('member')->after('user_id');
                });
            }
            
            // Check and add 'last_read_message_id' column
            if (!Schema::hasColumn('conversation_user', 'last_read_message_id')) {
                Schema::table('conversation_user', function (Blueprint $table) {
                    $table->foreignId('last_read_message_id')
                          ->nullable()
                          ->after('role')
                          ->constrained('messages')
                          ->onDelete('set null');
                });
            }
            
            // Check and add 'muted_until' column
            if (!Schema::hasColumn('conversation_user', 'muted_until')) {
                Schema::table('conversation_user', function (Blueprint $table) {
                    $table->timestamp('muted_until')->nullable()->after('last_read_message_id');
                });
            }
            
            // Check and add 'pinned_at' column
            if (!Schema::hasColumn('conversation_user', 'pinned_at')) {
                Schema::table('conversation_user', function (Blueprint $table) {
                    $table->timestamp('pinned_at')->nullable()->after('muted_until');
                });
            }
            
            // Check and add timestamps if they don't exist
            if (!Schema::hasColumn('conversation_user', 'created_at')) {
                Schema::table('conversation_user', function (Blueprint $table) {
                    $table->timestamps();
                });
            }
            
            // Check if the unique constraint exists using Laravel methods
            $this->addMissingIndexesAndConstraints();
        }
    }
    
    /**
     * Add missing indexes and constraints using Laravel methods
     */
    private function addMissingIndexesAndConstraints()
    {
        // Check if unique constraint exists
        $indexes = DB::select("SHOW INDEX FROM conversation_user WHERE Key_name = 'conversation_user_conversation_id_user_id_unique'");
        
        if (empty($indexes)) {
            Schema::table('conversation_user', function (Blueprint $table) {
                $table->unique(['conversation_id', 'user_id']);
            });
        }
        
        // Check foreign key constraints using information schema
        $this->addMissingForeignKeys();
    }
    
    /**
     * Add missing foreign key constraints using information schema
     */
    private function addMissingForeignKeys()
    {
        $tableName = 'conversation_user';
        $databaseName = DB::getDatabaseName();
        
        // Check conversation_id foreign key
        $conversationFk = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = 'conversation_id' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$databaseName, $tableName]);
        
        if (empty($conversationFk)) {
            Schema::table('conversation_user', function (Blueprint $table) {
                $table->foreign('conversation_id')
                      ->references('id')
                      ->on('conversations')
                      ->onDelete('cascade');
            });
        }
        
        // Check user_id foreign key
        $userFk = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = 'user_id' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$databaseName, $tableName]);
        
        if (empty($userFk)) {
            Schema::table('conversation_user', function (Blueprint $table) {
                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');
            });
        }
        
        // Check last_read_message_id foreign key (if column exists)
        if (Schema::hasColumn('conversation_user', 'last_read_message_id')) {
            $messageFk = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = ? 
                AND TABLE_NAME = ? 
                AND COLUMN_NAME = 'last_read_message_id' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$databaseName, $tableName]);
            
            if (empty($messageFk)) {
                Schema::table('conversation_user', function (Blueprint $table) {
                    $table->foreign('last_read_message_id')
                          ->references('id')
                          ->on('messages')
                          ->onDelete('set null');
                });
            }
        }
    }

    public function down()
    {
        // Safe down migration - only drop columns if they exist
        // This prevents data loss and errors
        
        if (Schema::hasColumn('conversation_user', 'pinned_at')) {
            Schema::table('conversation_user', function (Blueprint $table) {
                $table->dropColumn('pinned_at');
            });
        }
        
        if (Schema::hasColumn('conversation_user', 'muted_until')) {
            Schema::table('conversation_user', function (Blueprint $table) {
                $table->dropColumn('muted_until');
            });
        }
        
        if (Schema::hasColumn('conversation_user', 'last_read_message_id')) {
            Schema::table('conversation_user', function (Blueprint $table) {
                $table->dropForeign(['last_read_message_id']);
                $table->dropColumn('last_read_message_id');
            });
        }
        
        if (Schema::hasColumn('conversation_user', 'role')) {
            Schema::table('conversation_user', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
        
        // Don't drop the table to prevent data loss
        // Schema::dropIfExists('conversation_user');
    }
};