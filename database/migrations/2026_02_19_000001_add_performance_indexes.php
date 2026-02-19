<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ✅ MODERN: Database indexes for high-performance messaging
 * Optimizes queries for messages, conversations, and statuses
 * WhatsApp/Telegram-level performance optimization
 */
return new class extends Migration
{
    public function up(): void
    {
        // ✅ Messages table indexes for fast retrieval
        Schema::table('messages', function (Blueprint $table) {
            // Composite index for conversation message queries (most common)
            $table->index(['conversation_id', 'created_at'], 'idx_messages_conversation_time');
            
            // Index for sender queries
            $table->index('sender_id', 'idx_messages_sender');
            
            // Index for message ID lookups
            if (!Schema::hasColumn('messages', 'id_index')) {
                $table->index('id', 'idx_messages_id');
            }
        });

        // ✅ Message statuses indexes for read receipt queries
        Schema::table('message_statuses', function (Blueprint $table) {
            // Composite index for user's unread messages
            $table->index(['user_id', 'status', 'created_at'], 'idx_status_user_status_time');
            
            // Partial index concept: Add where clause in raw SQL for unread messages
            // Note: MySQL doesn't support partial indexes, but we can optimize queries with this composite index
        });

        // ✅ Conversations table indexes
        Schema::table('conversations', function (Blueprint $table) {
            // Index for user conversation queries
            if (Schema::hasColumn('conversations', 'user_one_id')) {
                $table->index('user_one_id', 'idx_conversations_user_one');
                $table->index('user_two_id', 'idx_conversations_user_two');
            }
            
            // Index for timestamp-based sorting
            $table->index('created_at', 'idx_conversations_created_at');
            $table->index('updated_at', 'idx_conversations_updated_at');
        });

        // ✅ Conversation-user pivot table indexes for fast lookups
        Schema::table('conversation_user', function (Blueprint $table) {
            // Composite index for user's conversations with unread counts
            $table->index(['user_id', 'archived_at', 'pinned_at'], 'idx_conv_user_archived_pinned');
            
            // Index for last read message tracking
            if (Schema::hasColumn('conversation_user', 'last_read_message_id')) {
                $table->index('last_read_message_id', 'idx_conv_user_last_read');
            }
        });

        // ✅ Add updated_at to conversations if not exists (for smart sync)
        if (!Schema::hasColumn('conversations', 'updated_at')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            });
        }

        // Backfill updated_at with created_at for existing records
        DB::statement('UPDATE conversations SET updated_at = created_at WHERE updated_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_conversation_time');
            $table->dropIndex('idx_messages_sender');
            if (Schema::hasIndex('messages', 'idx_messages_id')) {
                $table->dropIndex('idx_messages_id');
            }
        });

        Schema::table('message_statuses', function (Blueprint $table) {
            $table->dropIndex('idx_status_user_status_time');
        });

        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasIndex('conversations', 'idx_conversations_user_one')) {
                $table->dropIndex('idx_conversations_user_one');
            }
            if (Schema::hasIndex('conversations', 'idx_conversations_user_two')) {
                $table->dropIndex('idx_conversations_user_two');
            }
            $table->dropIndex('idx_conversations_created_at');
            if (Schema::hasIndex('conversations', 'idx_conversations_updated_at')) {
                $table->dropIndex('idx_conversations_updated_at');
            }
        });

        Schema::table('conversation_user', function (Blueprint $table) {
            $table->dropIndex('idx_conv_user_archived_pinned');
            if (Schema::hasIndex('conversation_user', 'idx_conv_user_last_read')) {
                $table->dropIndex('idx_conv_user_last_read');
            }
        });
    }
};
