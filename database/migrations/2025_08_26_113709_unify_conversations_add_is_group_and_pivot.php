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

        // ---- Backfill pivot from legacy user_one_id/user_two_id if present ----
        $hasUserOne = Schema::hasColumn('conversations', 'user_one_id');
        $hasUserTwo = Schema::hasColumn('conversations', 'user_two_id');

        if ($hasUserOne && $hasUserTwo) {
            $hasTimestamps = Schema::hasColumn('conversation_user', 'created_at')
                && Schema::hasColumn('conversation_user', 'updated_at');

            DB::table('conversations')
                ->select('id', 'user_one_id', 'user_two_id', 'created_by')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($hasTimestamps) {
                    $now = now();
                    foreach ($rows as $row) {
                        if (empty($row->user_one_id) || empty($row->user_two_id)) {
                            continue;
                        }

                        foreach ([(int)$row->user_one_id, (int)$row->user_two_id] as $uid) {
                            $values = ['role' => 'member'];
                            if ($hasTimestamps) {
                                $values['created_at'] = $now;
                                $values['updated_at'] = $now;
                            }
                            DB::table('conversation_user')->updateOrInsert(
                                ['conversation_id' => (int)$row->id, 'user_id' => $uid],
                                $values
                            );
                        }

                        DB::table('conversations')
                            ->where('id', $row->id)
                            ->update([
                                'is_group'   => false,
                                'created_by' => $row->created_by ?: (int)$row->user_one_id,
                            ]);
                    }
                });
        }
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
