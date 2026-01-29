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
        Schema::table('messages', function (Blueprint $table) {
            // Composite index for conversation_id + id (most common query pattern)
            // Speeds up: WHERE conversation_id = ? ORDER BY id
            $table->index(['conversation_id', 'id'], 'idx_conversation_id_id');
            
            // Composite index for conversation_id + created_at + id
            // Speeds up: WHERE conversation_id = ? AND created_at > ? ORDER BY id
            $table->index(['conversation_id', 'created_at', 'id'], 'idx_conversation_created_id');
            
            // Index for sender_id (for filtering messages from other users)
            if (!Schema::hasColumn('messages', 'sender_id')) {
                // Index might already exist, check first
                $table->index('sender_id', 'idx_sender_id');
            }
            
            // Index for delivered_at (for bulk updates)
            $table->index('delivered_at', 'idx_delivered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_conversation_id_id');
            $table->dropIndex('idx_conversation_created_id');
            $table->dropIndex('idx_sender_id');
            $table->dropIndex('idx_delivered_at');
        });
    }
};
