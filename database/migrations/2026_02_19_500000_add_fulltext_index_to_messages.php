<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Adds a MySQL FULLTEXT index on messages.body for fast SQL fallback search.
 * Meilisearch is the primary search backend; this index is used when Meilisearch
 * is not configured or unreachable.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only add if it doesn't already exist (idempotent)
        $exists = DB::select("SHOW INDEX FROM messages WHERE Key_name = 'messages_body_fulltext'");
        if (empty($exists)) {
            DB::statement('ALTER TABLE messages ADD FULLTEXT INDEX messages_body_fulltext (body)');
        }
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE messages DROP INDEX messages_body_fulltext');
        } catch (\Throwable) {
            // Index may not exist; ignore.
        }
    }
};
