<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * LOW PRIORITY - NICE TO HAVE FEATURES
     * 
     * 1. User verification/badges system
     * 2. Contact sync history
     * 3. Media download tracking
     * 4. Group join requests
     * 5. Message scheduling
     * 6. Message pinning
     * 7. Quick reply usage tracking
     * 8. Typing indicators (persistent)
     */
    public function up(): void
    {
        // ====================================================================
        // 1. USER VERIFICATION & BADGES SYSTEM
        // ====================================================================
        
        // Add verification fields to users table
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'verification_status')) {
                    $table->enum('verification_status', ['none', 'pending', 'verified', 'rejected'])
                        ->default('none')
                        ->after('is_verified');
                }
                
                if (!Schema::hasColumn('users', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable()->after('verification_status');
                }
                
                if (!Schema::hasColumn('users', 'verified_by')) {
                    $table->unsignedBigInteger('verified_by')->nullable()->after('verified_at');
                }
                
                if (!Schema::hasColumn('users', 'verification_notes')) {
                    $table->text('verification_notes')->nullable()->after('verified_by');
                }
            });
            
            // Add foreign key for verified_by
            if (!$this->foreignKeyExists('users', 'users_verified_by_foreign')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->foreign('verified_by')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                });
            }
            
            echo "✅ Added verification fields to users table\n";
        }
        
        // Create badges table
        if (!Schema::hasTable('user_badges')) {
            Schema::create('user_badges', function (Blueprint $table) {
                $table->id();
                $table->string('name', 50)->unique(); // 'verified', 'early_adopter', 'premium', etc.
                $table->string('display_name', 100); // Display name for UI
                $table->string('icon', 100)->nullable(); // Icon URL or emoji
                $table->string('color', 7)->default('#3B82F6'); // Hex color
                $table->text('description');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();
                
                $table->index('is_active');
            });
            echo "✅ Created user_badges table\n";
        }
        
        // Create badge assignments table
        if (!Schema::hasTable('user_badge_assignments')) {
            Schema::create('user_badge_assignments', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('badge_id');
                $table->timestamp('assigned_at');
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->text('assignment_notes')->nullable();
                
                $table->primary(['user_id', 'badge_id']);
                $table->index('badge_id');
                $table->index('assigned_at');
                
                // Foreign keys
                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                        
                    $table->foreign('assigned_by')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
                
                if (Schema::hasTable('user_badges')) {
                    $table->foreign('badge_id')
                        ->references('id')
                        ->on('user_badges')
                        ->cascadeOnDelete();
                }
            });
            echo "✅ Created user_badge_assignments table\n";
        }
        
        // ====================================================================
        // 2. CONTACT SYNC HISTORY
        // ====================================================================
        if (!Schema::hasTable('contact_sync_logs')) {
            Schema::create('contact_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->enum('source', ['google', 'phone', 'manual']); // Contact source
                $table->unsignedInteger('contacts_added')->default(0);
                $table->unsignedInteger('contacts_updated')->default(0);
                $table->unsignedInteger('contacts_deleted')->default(0);
                $table->timestamp('synced_at');
                $table->json('metadata')->nullable(); // Details about sync
                $table->boolean('was_successful')->default(true);
                $table->text('error_message')->nullable();
                
                $table->index(['user_id', 'synced_at']);
                $table->index('source');
                
                // Foreign key
                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                }
            });
            echo "✅ Created contact_sync_logs table\n";
        }
        
        // ====================================================================
        // 3. MEDIA DOWNLOAD TRACKING
        // ====================================================================
        if (Schema::hasTable('attachments')) {
            Schema::table('attachments', function (Blueprint $table) {
                if (!Schema::hasColumn('attachments', 'download_count')) {
                    $table->unsignedInteger('download_count')->default(0)->after('size');
                }
                
                if (!Schema::hasColumn('attachments', 'first_downloaded_at')) {
                    $table->timestamp('first_downloaded_at')->nullable()->after('download_count');
                }
                
                if (!Schema::hasColumn('attachments', 'last_downloaded_at')) {
                    $table->timestamp('last_downloaded_at')->nullable()->after('first_downloaded_at');
                }
                
                if (!Schema::hasColumn('attachments', 'unique_downloaders')) {
                    $table->unsignedInteger('unique_downloaders')->default(0)->after('last_downloaded_at');
                }
            });
            echo "✅ Added download tracking to attachments table\n";
        }
        
        // ====================================================================
        // 4. GROUP JOIN REQUESTS
        // ====================================================================
        if (!Schema::hasTable('group_join_requests')) {
            Schema::create('group_join_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('group_id');
                $table->unsignedBigInteger('user_id');
                $table->text('message')->nullable(); // Request message
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->timestamps();
                
                $table->unique(['group_id', 'user_id']);
                $table->index(['group_id', 'status']);
                $table->index(['user_id', 'status']);
                
                // Foreign keys
                if (Schema::hasTable('groups')) {
                    $table->foreign('group_id')
                        ->references('id')
                        ->on('groups')
                        ->cascadeOnDelete();
                }
                
                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                        
                    $table->foreign('reviewed_by')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
            });
            echo "✅ Created group_join_requests table\n";
        }
        
        // ====================================================================
        // 5. MESSAGE SCHEDULING
        // ====================================================================
        if (!Schema::hasTable('scheduled_messages')) {
            Schema::create('scheduled_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversation_id')->nullable();
                $table->unsignedBigInteger('group_id')->nullable();
                $table->unsignedBigInteger('user_id'); // Sender
                $table->text('body');
                $table->json('attachments')->nullable(); // Array of attachment IDs
                $table->unsignedBigInteger('reply_to_id')->nullable();
                $table->timestamp('scheduled_for');
                $table->enum('status', ['pending', 'sent', 'failed', 'cancelled'])->default('pending');
                $table->unsignedBigInteger('sent_message_id')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
                
                $table->index(['status', 'scheduled_for']);
                $table->index('user_id');
                $table->index('conversation_id');
                $table->index('group_id');
                
                // Foreign keys
                if (Schema::hasTable('conversations')) {
                    $table->foreign('conversation_id')
                        ->references('id')
                        ->on('conversations')
                        ->cascadeOnDelete();
                }
                
                if (Schema::hasTable('groups')) {
                    $table->foreign('group_id')
                        ->references('id')
                        ->on('groups')
                        ->cascadeOnDelete();
                }
                
                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                }
                
                if (Schema::hasTable('messages')) {
                    $table->foreign('sent_message_id')
                        ->references('id')
                        ->on('messages')
                        ->nullOnDelete();
                }
            });
            echo "✅ Created scheduled_messages table\n";
        }
        
        // ====================================================================
        // 6. MESSAGE PINNING (Group Context)
        // ====================================================================
        if (!Schema::hasTable('group_pinned_messages')) {
            Schema::create('group_pinned_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('group_id');
                $table->unsignedBigInteger('message_id');
                $table->unsignedBigInteger('pinned_by');
                $table->timestamp('pinned_at');
                $table->unsignedTinyInteger('pin_order')->default(0); // For multiple pins
                
                $table->unique(['group_id', 'message_id']);
                $table->index(['group_id', 'pinned_at']);
                
                // Foreign keys
                if (Schema::hasTable('groups')) {
                    $table->foreign('group_id')
                        ->references('id')
                        ->on('groups')
                        ->cascadeOnDelete();
                }
                
                if (Schema::hasTable('group_messages')) {
                    $table->foreign('message_id')
                        ->references('id')
                        ->on('group_messages')
                        ->cascadeOnDelete();
                }
                
                if (Schema::hasTable('users')) {
                    $table->foreign('pinned_by')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                }
            });
            echo "✅ Created group_pinned_messages table\n";
        }
        
        // Add pinned message to conversation_user (1-on-1)
        if (Schema::hasTable('conversation_user')) {
            Schema::table('conversation_user', function (Blueprint $table) {
                if (!Schema::hasColumn('conversation_user', 'pinned_message_id')) {
                    $table->unsignedBigInteger('pinned_message_id')->nullable()->after('pinned_at');
                    
                    if (Schema::hasTable('messages')) {
                        $table->foreign('pinned_message_id')
                            ->references('id')
                            ->on('messages')
                            ->nullOnDelete();
                    }
                }
            });
            echo "✅ Added pinned_message_id to conversation_user\n";
        }
        
        // ====================================================================
        // 7. QUICK REPLY USAGE TRACKING
        // ====================================================================
        if (Schema::hasTable('quick_replies')) {
            Schema::table('quick_replies', function (Blueprint $table) {
                if (!Schema::hasColumn('quick_replies', 'usage_count')) {
                    $table->unsignedInteger('usage_count')->default(0)->after('message');
                }
                
                if (!Schema::hasColumn('quick_replies', 'last_used_at')) {
                    $table->timestamp('last_used_at')->nullable()->after('usage_count');
                }
            });
            echo "✅ Added usage tracking to quick_replies\n";
        }
        
        // ====================================================================
        // 8. TYPING INDICATORS (Persistent Storage)
        // ====================================================================
        if (!Schema::hasTable('typing_indicators')) {
            Schema::create('typing_indicators', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversation_id')->nullable();
                $table->unsignedBigInteger('group_id')->nullable();
                $table->unsignedBigInteger('user_id');
                $table->timestamp('started_at');
                $table->timestamp('expires_at'); // Auto-expire after 5-10 seconds
                
                $table->index(['conversation_id', 'user_id']);
                $table->index(['group_id', 'user_id']);
                $table->index('expires_at'); // For cleanup job
                
                // Foreign keys
                if (Schema::hasTable('conversations')) {
                    $table->foreign('conversation_id')
                        ->references('id')
                        ->on('conversations')
                        ->cascadeOnDelete();
                }
                
                if (Schema::hasTable('groups')) {
                    $table->foreign('group_id')
                        ->references('id')
                        ->on('groups')
                        ->cascadeOnDelete();
                }
                
                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                }
            });
            echo "✅ Created typing_indicators table\n";
        }
        
        echo "\n🎉 LOW PRIORITY FEATURES COMPLETED!\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order
        Schema::dropIfExists('typing_indicators');
        Schema::dropIfExists('group_pinned_messages');
        Schema::dropIfExists('scheduled_messages');
        Schema::dropIfExists('group_join_requests');
        Schema::dropIfExists('contact_sync_logs');
        Schema::dropIfExists('user_badge_assignments');
        Schema::dropIfExists('user_badges');
        
        // Remove columns from existing tables
        if (Schema::hasTable('conversation_user')) {
            Schema::table('conversation_user', function (Blueprint $table) {
                if (Schema::hasColumn('conversation_user', 'pinned_message_id')) {
                    $table->dropForeign(['pinned_message_id']);
                    $table->dropColumn('pinned_message_id');
                }
            });
        }
        
        if (Schema::hasTable('quick_replies')) {
            Schema::table('quick_replies', function (Blueprint $table) {
                $columns = ['usage_count', 'last_used_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('quick_replies', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
        
        if (Schema::hasTable('attachments')) {
            Schema::table('attachments', function (Blueprint $table) {
                $columns = ['download_count', 'first_downloaded_at', 'last_downloaded_at', 'unique_downloaders'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('attachments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
        
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'verified_by')) {
                    $table->dropForeign(['verified_by']);
                }
                
                $columns = ['verification_status', 'verified_at', 'verified_by', 'verification_notes'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
    
    /**
     * Check if a foreign key exists
     */
    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        try {
            $connection = Schema::getConnection();
            $database = $connection->getDatabaseName();
            
            $result = $connection->select(
                "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                 WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
                [$database, $table, $foreignKey]
            );
            
            return $result[0]->count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
};
