<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * MySQL stores `statuses.type` as ENUM; SQLite uses a string column — no change needed there.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE statuses MODIFY COLUMN type ENUM('text','image','video','audio') NOT NULL"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE statuses MODIFY COLUMN type ENUM('text','image','video') NOT NULL"
        );
    }
};
