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
     * HIGH PRIORITY SCHEMA IMPROVEMENTS
     * 
     * 1. Device token improvements (push notification reliability)
     * 2. User activity tracking (security & UX)
     * 3. Soft deletes for core tables (data retention & GDPR)
     * 4. Audit logs (compliance & security)
     * 5. User privacy settings (granular control)
     * 6. Notification preferences (user control)
     */
    public function up(): void
    {
        // ====================================================================
        // 1. DEVICE TOKENS - Add missing columns for push notification management
        // ====================================================================
        if (Schema::hasTable('device_tokens')) {
            Schema::table('device_tokens', function (Blueprint $table) {
                if (!Schema::hasColumn('device_tokens', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('token');
                }
                
                if (!Schema::hasColumn('device_tokens', 'last_used_at')) {
                    $table->timestamp('last_used_at')->nullable()->after('is_active');
                }
                
                if (!Schema::hasColumn('device_tokens', 'device_id')) {
                    $table->string('device_id', 100)->nullable()->after('platform');
                }
                
                if (!Schema::hasColumn('device_tokens', 'app_version')) {
                    $table->string('app_version', 20)->nullable()->after('device_id');
                }
                
                if (!Schema::hasColumn('device_tokens', 'device_model')) {
                    $table->string('device_model', 100)->nullable()->after('device_id');
                }
            });
            
            echo "✅ Enhanced device_tokens table\n";
        }
        
        // ====================================================================
        // 2. USERS - Add activity tracking and security columns
        // ====================================================================
        Schema::table('users', function (Blueprint $table) {
            // Login tracking
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('password');
            }
            
            if (!Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            }
            
            if (!Schema::hasColumn('users', 'last_login_user_agent')) {
                $table->string('last_login_user_agent', 255)->nullable()->after('last_login_ip');
            }
            
            if (!Schema::hasColumn('users', 'last_login_country')) {
                $table->char('last_login_country', 2)->nullable()->after('last_login_user_agent');
            }
            
            // Security
            if (!Schema::hasColumn('users', 'failed_login_attempts')) {
                $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('password');
            }
            
            if (!Schema::hasColumn('users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            }
            
            if (!Schema::hasColumn('users', 'total_logins')) {
                $table->unsignedInteger('total_logins')->default(0)->after('last_login_country');
            }
            
            // Soft deletes
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
                $table->string('deletion_reason', 100)->nullable()->after('deleted_at');
                $table->timestamp('scheduled_deletion_at')->nullable()->after('deletion_reason'); // GDPR 30-day grace
            }
        });
        
        // Add indexes for performance
        if (!$this->indexExists('users', 'users_last_login_at_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('last_login_at');
            });
        }
        
        if (!$this->indexExists('users', 'users_last_seen_at_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('last_seen_at');
            });
        }
        
        if (!$this->indexExists('users', 'users_locked_until_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('locked_until');
            });
        }
        
        if (!$this->indexExists('users', 'users_deleted_at_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('deleted_at');
            });
        }
        
        echo "✅ Enhanced users table with activity tracking and soft deletes\n";
        
        // ====================================================================
        // 3. CONVERSATIONS & GROUPS - Add soft deletes
        // ====================================================================
        if (Schema::hasTable('conversations')) {
            Schema::table('conversations', function (Blueprint $table) {
                if (!Schema::hasColumn('conversations', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
            echo "✅ Added soft deletes to conversations\n";
        }
        
        if (Schema::hasTable('groups')) {
            Schema::table('groups', function (Blueprint $table) {
                if (!Schema::hasColumn('groups', 'deleted_at')) {
                    $table->softDeletes();
                    $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
                    
                    if (Schema::hasTable('users')) {
                        $table->foreign('deleted_by')
                            ->references('id')
                            ->on('users')
                            ->nullOnDelete();
                    }
                }
            });
            echo "✅ Added soft deletes to groups\n";
        }
        
        // ====================================================================
        // 4. AUDIT LOGS - Create table for compliance and security
        // ====================================================================
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 100); // created, updated, deleted, login, logout, etc.
                $table->string('auditable_type')->nullable(); // Model class name
                $table->unsignedBigInteger('auditable_id')->nullable(); // Model ID
                $table->text('description')->nullable(); // Human-readable description
                $table->json('old_values')->nullable(); // Before state
                $table->json('new_values')->nullable(); // After state
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->string('url', 500)->nullable();
                $table->timestamps();
                
                // Indexes
                $table->index(['auditable_type', 'auditable_id']);
                $table->index(['user_id', 'created_at']);
                $table->index('created_at');
                $table->index('action');
                
                // Foreign key
                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
            });
            echo "✅ Created audit_logs table\n";
        }
        
        // ====================================================================
        // 5. USER PRIVACY SETTINGS - Granular privacy controls
        // ====================================================================
        if (!Schema::hasTable('user_privacy_settings')) {
            Schema::create('user_privacy_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                
                // Who can...
                $table->enum('who_can_message', ['everyone', 'contacts', 'nobody'])->default('everyone');
                $table->enum('who_can_see_profile', ['everyone', 'contacts', 'nobody'])->default('everyone');
                $table->enum('who_can_see_last_seen', ['everyone', 'contacts', 'nobody'])->default('everyone');
                $table->enum('who_can_see_status', ['everyone', 'contacts', 'contacts_except', 'only_share_with'])->default('everyone');
                $table->enum('who_can_add_to_groups', ['everyone', 'contacts', 'admins_only'])->default('everyone');
                $table->enum('who_can_call', ['everyone', 'contacts', 'nobody'])->default('everyone');
                $table->enum('profile_photo_visibility', ['everyone', 'contacts', 'nobody'])->default('everyone');
                $table->enum('about_visibility', ['everyone', 'contacts', 'nobody'])->default('everyone');
                
                // Features
                $table->boolean('send_read_receipts')->default(true);
                $table->boolean('send_typing_indicator')->default(true);
                $table->boolean('show_online_status')->default(true);
                
                $table->timestamps();
                
                // Foreign key
                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                }
            });
            echo "✅ Created user_privacy_settings table\n";
        }
        
        // ====================================================================
        // 6. NOTIFICATION PREFERENCES - Per-channel notification settings
        // ====================================================================
        if (!Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                
                // Push notifications
                $table->boolean('push_messages')->default(true);
                $table->boolean('push_group_messages')->default(true);
                $table->boolean('push_calls')->default(true);
                $table->boolean('push_status_updates')->default(true);
                $table->boolean('push_reactions')->default(true);
                $table->boolean('push_mentions')->default(true);
                
                // Email notifications
                $table->boolean('email_messages')->default(false);
                $table->boolean('email_weekly_digest')->default(true);
                $table->boolean('email_security_alerts')->default(true);
                $table->boolean('email_marketing')->default(false);
                
                // In-app notifications
                $table->boolean('show_message_preview')->default(true);
                $table->boolean('notification_sound')->default(true);
                $table->boolean('vibrate')->default(true);
                $table->boolean('led_notification')->default(true);
                
                // Quiet hours
                $table->time('quiet_hours_start')->nullable(); // e.g., 22:00
                $table->time('quiet_hours_end')->nullable();   // e.g., 07:00
                $table->boolean('quiet_hours_enabled')->default(false);
                
                $table->timestamps();
                
                // Foreign key
                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                }
            });
            echo "✅ Created notification_preferences table\n";
        }
        
        // ====================================================================
        // 7. MESSAGE EDIT HISTORY - Track message edits
        // ====================================================================
        if (!Schema::hasTable('message_edit_history')) {
            Schema::create('message_edit_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('message_id');
                $table->text('old_body');
                $table->text('new_body');
                $table->unsignedBigInteger('edited_by');
                $table->timestamp('edited_at');
                
                // Indexes
                $table->index(['message_id', 'edited_at']);
                
                // Foreign keys
                if (Schema::hasTable('messages')) {
                    $table->foreign('message_id')
                        ->references('id')
                        ->on('messages')
                        ->cascadeOnDelete();
                }
                
                if (Schema::hasTable('users')) {
                    $table->foreign('edited_by')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                }
            });
            
            // Add edit tracking to messages table
            if (Schema::hasTable('messages')) {
                Schema::table('messages', function (Blueprint $table) {
                    if (!Schema::hasColumn('messages', 'edit_count')) {
                        $table->unsignedTinyInteger('edit_count')->default(0)->after('body');
                    }
                });
            }
            
            echo "✅ Created message_edit_history table\n";
        }
        
        echo "\n🎉 HIGH PRIORITY IMPROVEMENTS COMPLETED!\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop new tables (in reverse order)
        Schema::dropIfExists('message_edit_history');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('user_privacy_settings');
        Schema::dropIfExists('audit_logs');
        
        // Remove columns from existing tables
        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if (Schema::hasColumn('messages', 'edit_count')) {
                    $table->dropColumn('edit_count');
                }
            });
        }
        
        if (Schema::hasTable('groups')) {
            Schema::table('groups', function (Blueprint $table) {
                if (Schema::hasColumn('groups', 'deleted_by')) {
                    $table->dropForeign(['deleted_by']);
                    $table->dropColumn('deleted_by');
                }
                if (Schema::hasColumn('groups', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
        
        if (Schema::hasTable('conversations')) {
            Schema::table('conversations', function (Blueprint $table) {
                if (Schema::hasColumn('conversations', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
        
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $columns = ['last_login_at', 'last_login_ip', 'last_login_user_agent', 
                           'last_login_country', 'failed_login_attempts', 'locked_until', 
                           'total_logins', 'deleted_at', 'deletion_reason', 'scheduled_deletion_at'];
                
                foreach ($columns as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
        
        if (Schema::hasTable('device_tokens')) {
            Schema::table('device_tokens', function (Blueprint $table) {
                $columns = ['is_active', 'last_used_at', 'device_id', 'app_version', 'device_model'];
                
                foreach ($columns as $column) {
                    if (Schema::hasColumn('device_tokens', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
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
            return false;
        }
    }
};
