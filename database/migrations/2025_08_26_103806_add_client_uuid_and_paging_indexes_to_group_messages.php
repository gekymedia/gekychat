<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('group_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('group_messages', 'client_uuid')) {
                $table->uuid('client_uuid')->nullable()->after('id');
            }
        });

        // Composite index for cursor paging (MySQL 8 has no CREATE INDEX IF NOT EXISTS)
        try {
            DB::statement('CREATE INDEX group_messages_group_created_id ON group_messages (group_id, created_at, id)');
        } catch (\Throwable $e) {
        }

        // Optional: unique-ish safety on client_uuid (allow nulls)
        try {
            DB::statement('CREATE UNIQUE INDEX group_messages_client_uuid_unique ON group_messages (client_uuid)');
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        // Drop indexes if your MySQL supports IF EXISTS
        try { DB::statement('DROP INDEX group_messages_group_created_id ON group_messages'); } catch (\Throwable $e) {}
        try { DB::statement('DROP INDEX group_messages_client_uuid_unique ON group_messages'); } catch (\Throwable $e) {}

        Schema::table('group_messages', function (Blueprint $table) {
            if (Schema::hasColumn('group_messages', 'client_uuid')) {
                $table->dropColumn('client_uuid');
            }
        });
    }
};
