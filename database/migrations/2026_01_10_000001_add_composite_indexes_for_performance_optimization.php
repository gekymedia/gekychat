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
     * This migration adds composite indexes to improve query performance
     * across the most frequently queried tables in the application.
     */
    public function up(): void
    {
        // ===== MESSAGES TABLE INDEXES =====
        
        // Index for conversation message listing with sender filter
        $this->createIndexIfNotExists(
            'messages',
            'idx_messages_conversation_sender',
            'conversation_id, sender_id, created_at'
        );
        
        // Index for deleted messages filtering (only if deleted_for_everyone_at column exists)
        if (Schema::hasColumn('messages', 'deleted_for_everyone_at')) {
            $this->createIndexIfNotExists(
                'messages',
                'idx_messages_deleted',
                'conversation_id, deleted_for_everyone_at'
            );
        }
        
        // Index for reply_to queries
        $this->createIndexIfNotExists(
            'messages',
            'idx_messages_reply_to',
            'reply_to_id, created_at'
        );
        
        // ===== GROUP_MESSAGES TABLE INDEXES =====
        
        // Index for group message listing with sender
        $this->createIndexIfNotExists(
            'group_messages',
            'idx_group_messages_group_sender',
            'group_id, sender_id, created_at'
        );
        
        // Index for deleted group messages (only if columns exist)
        if (Schema::hasColumn('group_messages', 'deleted_for_everyone_at') || Schema::hasColumn('group_messages', 'deleted_at')) {
            $deletedCol = Schema::hasColumn('group_messages', 'deleted_for_everyone_at') ? 'deleted_for_everyone_at' : 'deleted_at';
            $this->createIndexIfNotExists(
                'group_messages',
                'idx_group_messages_deleted',
                "group_id, {$deletedCol}"
            );
        }
        
        // ===== CONVERSATION_USER TABLE INDEXES =====
        
        // Index for user's conversation list with archiving and pinning
        $this->createIndexIfNotExists(
            'conversation_user',
            'idx_conversation_user_list',
            'user_id, is_archived, is_pinned, updated_at'
        );
        
        // Index for pinned conversations only
        $this->createIndexIfNotExists(
            'conversation_user',
            'idx_conversation_user_pinned',
            'user_id, is_pinned, updated_at'
        );
        
        // Index for archived conversations
        $this->createIndexIfNotExists(
            'conversation_user',
            'idx_conversation_user_archived',
            'user_id, is_archived, updated_at'
        );
        
        // ===== STATUSES TABLE INDEXES =====
        
        // Index for user's active statuses (not expired)
        $this->createIndexIfNotExists(
            'statuses',
            'idx_statuses_user_active',
            'user_id, expires_at, created_at'
        );
        
        // Index for status discovery feed
        $this->createIndexIfNotExists(
            'statuses',
            'idx_statuses_expires_created',
            'expires_at, created_at'
        );
        
        // ===== STATUS_VIEWS TABLE INDEXES =====
        
        // Index for status viewer list
        $this->createIndexIfNotExists(
            'status_views',
            'idx_status_views_status_time',
            'status_id, viewed_at'
        );
        
        // Index for user's viewed statuses
        $this->createIndexIfNotExists(
            'status_views',
            'idx_status_views_user_status',
            'user_id, status_id, viewed_at'
        );
        
        // Index for stealth views filtering
        $this->createIndexIfNotExists(
            'status_views',
            'idx_status_views_stealth',
            'status_id, stealth, viewed_at'
        );
        
        // ===== CALL_SESSIONS TABLE INDEXES =====
        
        // Index for caller's call history
        $this->createIndexIfNotExists(
            'call_sessions',
            'idx_call_sessions_caller',
            'caller_id, started_at'
        );
        
        // Index for callee's call history
        $this->createIndexIfNotExists(
            'call_sessions',
            'idx_call_sessions_callee',
            'callee_id, started_at'
        );
        
        // Index for active calls by status
        $this->createIndexIfNotExists(
            'call_sessions',
            'idx_call_sessions_status',
            'status, started_at'
        );
        
        // Index for group calls
        $this->createIndexIfNotExists(
            'call_sessions',
            'idx_call_sessions_group',
            'group_id, started_at'
        );
        
        // ===== CONTACTS TABLE INDEXES =====
        
        // Index for user's contact list
        $this->createIndexIfNotExists(
            'contacts',
            'idx_contacts_user_contact',
            'user_id, contact_user_id, created_at'
        );
        
        // Index for blocked contacts
        $this->createIndexIfNotExists(
            'contacts',
            'idx_contacts_blocked',
            'user_id, is_blocked, created_at'
        );
        
        // ===== MESSAGE_STATUSES TABLE INDEXES =====
        
        // Index for message read status queries
        $this->createIndexIfNotExists(
            'message_statuses',
            'idx_message_statuses_user_status',
            'user_id, message_id, status'
        );
        
        // Index for unread message counts
        $this->createIndexIfNotExists(
            'message_statuses',
            'idx_message_statuses_message',
            'message_id, status, updated_at'
        );
        
        // ===== GROUP_MESSAGE_STATUSES TABLE INDEXES =====
        
        // Index for group message read status
        $this->createIndexIfNotExists(
            'group_message_statuses',
            'idx_group_msg_statuses_user',
            'user_id, group_message_id, status'
        );
        
        // Index for group message read receipts
        $this->createIndexIfNotExists(
            'group_message_statuses',
            'idx_group_msg_statuses_message',
            'group_message_id, status, updated_at'
        );
        
        // ===== LABELS TABLE INDEXES =====
        
        // Index for user's labels
        $this->createIndexIfNotExists(
            'labels',
            'idx_labels_user',
            'user_id, created_at'
        );
        
        // ===== CONVERSATION_LABEL TABLE INDEXES =====
        
        // Index for conversation's labels
        $this->createIndexIfNotExists(
            'conversation_label',
            'idx_conversation_label_conv',
            'conversation_id, label_id'
        );
        
        // Index for label's conversations
        $this->createIndexIfNotExists(
            'conversation_label',
            'idx_conversation_label_label',
            'label_id, conversation_id'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop all indexes created in up()
        $indexes = [
            // Messages
            ['messages', 'idx_messages_conversation_sender'],
            ['messages', 'idx_messages_deleted'],
            ['messages', 'idx_messages_reply_to'],
            
            // Group Messages
            ['group_messages', 'idx_group_messages_group_sender'],
            ['group_messages', 'idx_group_messages_deleted'],
            
            // Conversation User
            ['conversation_user', 'idx_conversation_user_list'],
            ['conversation_user', 'idx_conversation_user_pinned'],
            ['conversation_user', 'idx_conversation_user_archived'],
            
            // Statuses
            ['statuses', 'idx_statuses_user_active'],
            ['statuses', 'idx_statuses_expires_created'],
            
            // Status Views
            ['status_views', 'idx_status_views_status_time'],
            ['status_views', 'idx_status_views_user_status'],
            ['status_views', 'idx_status_views_stealth'],
            
            // Call Sessions
            ['call_sessions', 'idx_call_sessions_caller'],
            ['call_sessions', 'idx_call_sessions_callee'],
            ['call_sessions', 'idx_call_sessions_status'],
            ['call_sessions', 'idx_call_sessions_group'],
            
            // Contacts
            ['contacts', 'idx_contacts_user_contact'],
            ['contacts', 'idx_contacts_blocked'],
            
            // Message Statuses
            ['message_statuses', 'idx_message_statuses_user_status'],
            ['message_statuses', 'idx_message_statuses_message'],
            
            // Group Message Statuses
            ['group_message_statuses', 'idx_group_msg_statuses_user'],
            ['group_message_statuses', 'idx_group_msg_statuses_message'],
            
            // Labels
            ['labels', 'idx_labels_user'],
            
            // Conversation Label
            ['conversation_label', 'idx_conversation_label_conv'],
            ['conversation_label', 'idx_conversation_label_label'],
        ];
        
        foreach ($indexes as [$table, $indexName]) {
            $this->dropIndexIfExists($table, $indexName);
        }
    }
    
    /**
     * Create an index if it doesn't already exist and all columns exist
     */
    private function createIndexIfNotExists(string $table, string $indexName, string $columns): void
    {
        if (!$this->indexExists($table, $indexName)) {
            // Check if all columns in the index exist
            $columnList = array_map('trim', explode(',', $columns));
            $allColumnsExist = true;
            foreach ($columnList as $column) {
                if (!Schema::hasColumn($table, trim($column))) {
                    $allColumnsExist = false;
                    break;
                }
            }
            
            if ($allColumnsExist) {
                try {
                    DB::statement("CREATE INDEX {$indexName} ON {$table} ({$columns})");
                } catch (\Exception $e) {
                    // Index might already exist or there's another issue - skip it
                    echo "⚠️ Could not create index {$indexName} on {$table}: {$e->getMessage()}\n";
                }
            } else {
                echo "⚠️ Skipping index {$indexName} on {$table} - some columns don't exist\n";
            }
        }
    }
    
    /**
     * Drop an index if it exists
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            DB::statement("DROP INDEX {$indexName} ON {$table}");
        }
    }
    
    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select("
            SELECT COUNT(*) as count
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
            AND table_name = ?
            AND index_name = ?
        ", [$table, $indexName]);
        
        return $result[0]->count > 0;
    }
};
