<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- conversations: add unified columns (idempotent) ----
        Schema::table('conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('conversations', 'is_group')) {
                $table->boolean('is_group')->default(false)->index()->after('id');
            }
            if (!Schema::hasColumn('conversations', 'name')) {
                $table->string('name')->nullable()->after('is_group');
            }
            if (!Schema::hasColumn('conversations', 'avatar_path')) {
                $table->string('avatar_path')->nullable()->after('name');
            }
            if (!Schema::hasColumn('conversations', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('avatar_path');
            }
        });

        // ---- conversation_user: create or upgrade ----
        if (!Schema::hasTable('conversation_user')) {
            Schema::create('conversation_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role', 16)->default('member');
                $table->foreignId('last_read_message_id')->nullable()->constrained('messages')->nullOnDelete();
                $table->timestamp('muted_until')->nullable();
                $table->timestamp('pinned_at')->nullable();
                $table->timestamps();

                $table->unique(['conversation_id', 'user_id']);
                $table->index(['user_id', 'pinned_at']);
            });
        } else {
            Schema::table('conversation_user', function (Blueprint $table) {
                if (!Schema::hasColumn('conversation_user', 'role')) {
                    $table->string('role', 16)->default('member')->after('user_id');
                }
                if (!Schema::hasColumn('conversation_user', 'last_read_message_id')) {
                    $table->foreignId('last_read_message_id')->nullable()->after('role')
                        ->constrained('messages')->nullOnDelete();
                }
                if (!Schema::hasColumn('conversation_user', 'muted_until')) {
                    $table->timestamp('muted_until')->nullable()->after('last_read_message_id');
                }
                if (!Schema::hasColumn('conversation_user', 'pinned_at')) {
                    $table->timestamp('pinned_at')->nullable()->after('muted_until');
                }
                // Add timestamps if missing
                if (!Schema::hasColumn('conversation_user', 'created_at')) {
                    $table->timestamp('created_at')->nullable()->after('pinned_at');
                }
                if (!Schema::hasColumn('conversation_user', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable()->after('created_at');
                }
            });
        }

        /*
         * The original migration included logic to backfill the pivot table and
         * conversation metadata from legacy `user_one_id` and `user_two_id`
         * columns. This project does not use those legacy columns, so we
         * deliberately omit the backfill to avoid referencing non‑existent
         * columns. If you are migrating a legacy installation, please add your
         * own data migration here to seed the `conversation_user` table and set
         * the `is_group` and `created_by` fields on conversations.
         */
    }

    public function down(): void
    {
        // Drop/rollback pivot extras
        if (Schema::hasTable('conversation_user')) {
            Schema::table('conversation_user', function (Blueprint $table) {
                if (Schema::hasColumn('conversation_user', 'last_read_message_id')) {
                    $table->dropConstrainedForeignId('last_read_message_id');
                }
            });
            // (We leave the table; removing it could orphan data. If you truly want to drop it:)
            // Schema::dropIfExists('conversation_user');
        }

        // Remove unified columns from conversations
        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            foreach (['avatar_path','name','is_group'] as $col) {
                if (Schema::hasColumn('conversations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
