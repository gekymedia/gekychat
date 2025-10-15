<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Add nullable client_uuid + unique index (safe even with NULLs in MySQL)
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'client_uuid')) {
                $table->uuid('client_uuid')->nullable()->after('id');
                // Name the index explicitly for portability
                $table->unique('client_uuid', 'messages_client_uuid_unique');
            }
        });

        // 2) Ensure composite paging index exists without Doctrine
        $indexName = 'messages_conversation_id_created_at_id_index';

        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'messages')
            ->where('index_name', $indexName)
            ->exists();

        if (!$exists) {
            DB::statement("CREATE INDEX {$indexName} ON messages (conversation_id, created_at, id)");
        }
    }

    public function down(): void
    {
        // Drop composite index if present
        $indexName = 'messages_conversation_id_created_at_id_index';

        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'messages')
            ->where('index_name', $indexName)
            ->exists();

        if ($exists) {
            DB::statement("DROP INDEX {$indexName} ON messages");
        }

        // Drop client_uuid + unique
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'client_uuid')) {
                // Drop unique by name if present
                try { $table->dropUnique('messages_client_uuid_unique'); } catch (\Throwable $e) {}
                $table->dropColumn('client_uuid');
            }
        });
    }
};
