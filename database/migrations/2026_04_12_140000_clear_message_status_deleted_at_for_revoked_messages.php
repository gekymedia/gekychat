<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Legacy "delete for everyone" set message_statuses.deleted_at for all participants,
     * which hid the row entirely. Tombstones require those flags cleared for revoked messages.
     */
    public function up(): void
    {
        DB::statement(
            'UPDATE message_statuses SET deleted_at = NULL
             WHERE deleted_at IS NOT NULL
               AND message_id IN (SELECT id FROM messages WHERE deleted_for_everyone_at IS NOT NULL)'
        );
    }

    public function down(): void
    {
        // Irreversible data migration.
    }
};
