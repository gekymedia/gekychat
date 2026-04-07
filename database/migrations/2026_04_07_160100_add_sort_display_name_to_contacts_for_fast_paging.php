<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contacts')) {
            return;
        }

        // Generated sort key keeps contact ordering stable and index-friendly:
        // LOWER(COALESCE(NULLIF(display_name, ''), normalized_phone)).
        if (!Schema::hasColumn('contacts', 'sort_display_name')) {
            DB::statement(
                "ALTER TABLE `contacts` ADD COLUMN `sort_display_name` VARCHAR(255) " .
                "GENERATED ALWAYS AS (LOWER(COALESCE(NULLIF(`display_name`, ''), `normalized_phone`))) STORED"
            );
        }

        // Composite index matches the common contacts list query:
        // WHERE user_id=? AND is_deleted=? ORDER BY sort_display_name, id LIMIT/OFFSET ...
        DB::statement(
            "CREATE INDEX `contacts_user_deleted_sort_id_idx` " .
            "ON `contacts` (`user_id`, `is_deleted`, `sort_display_name`, `id`)"
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('contacts')) {
            return;
        }

        try {
            DB::statement("DROP INDEX `contacts_user_deleted_sort_id_idx` ON `contacts`");
        } catch (\Throwable $e) {
            // no-op if index doesn't exist
        }

        if (Schema::hasColumn('contacts', 'sort_display_name')) {
            DB::statement("ALTER TABLE `contacts` DROP COLUMN `sort_display_name`");
        }
    }
};

