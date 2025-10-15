<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Core fields used by the model/controller
            if (!Schema::hasColumn('messages', 'is_encrypted')) {
                $table->boolean('is_encrypted')->default(false)->after('body');
            }

            if (!Schema::hasColumn('messages', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('is_encrypted');
            }

            if (!Schema::hasColumn('messages', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('expires_at');
            }

            if (!Schema::hasColumn('messages', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('delivered_at');
            }

            // Replies / forwards
            if (!Schema::hasColumn('messages', 'reply_to')) {
                $table->foreignId('reply_to')->nullable()->after('sender_id')
                      ->constrained('messages')->nullOnDelete();
            }

            if (!Schema::hasColumn('messages', 'forwarded_from_id')) {
                $table->foreignId('forwarded_from_id')->nullable()->after('reply_to')
                      ->constrained('messages')->nullOnDelete();
            }

            if (!Schema::hasColumn('messages', 'forward_chain')) {
                // JSON for MySQL 5.7+/MariaDB 10.2.7+, else fallback to TEXT
                try {
                    $table->json('forward_chain')->nullable()->after('forwarded_from_id');
                } catch (\Throwable $e) {
                    $table->text('forward_chain')->nullable()->after('forwarded_from_id');
                }
            }

            // Soft delete-for-user feature used by scopeVisibleTo()
            if (!Schema::hasColumn('messages', 'deleted_for_user_id')) {
                $table->foreignId('deleted_for_user_id')->nullable()->after('read_at')
                      ->constrained('users')->nullOnDelete()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Drop FKs safely if present
            if (Schema::hasColumn('messages', 'reply_to')) {
                try { $table->dropForeign(['reply_to']); } catch (\Throwable $e) {}
                $table->dropColumn('reply_to');
            }

            if (Schema::hasColumn('messages', 'forwarded_from_id')) {
                try { $table->dropForeign(['forwarded_from_id']); } catch (\Throwable $e) {}
                $table->dropColumn('forwarded_from_id');
            }

            if (Schema::hasColumn('messages', 'deleted_for_user_id')) {
                try { $table->dropForeign(['deleted_for_user_id']); } catch (\Throwable $e) {}
                $table->dropColumn('deleted_for_user_id');
            }

            if (Schema::hasColumn('messages', 'forward_chain')) {
                $table->dropColumn('forward_chain');
            }

            if (Schema::hasColumn('messages', 'read_at')) {
                $table->dropColumn('read_at');
            }

            if (Schema::hasColumn('messages', 'delivered_at')) {
                $table->dropColumn('delivered_at');
            }

            if (Schema::hasColumn('messages', 'expires_at')) {
                $table->dropColumn('expires_at');
            }

            if (Schema::hasColumn('messages', 'is_encrypted')) {
                $table->dropColumn('is_encrypted');
            }
        });
    }
};
