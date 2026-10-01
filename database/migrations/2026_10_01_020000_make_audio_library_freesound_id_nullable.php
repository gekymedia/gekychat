<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow locally hosted CC0 tracks that are not from Freesound.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite recreates columns awkwardly; skip if already nullable via fresh migrate.
            return;
        }

        DB::statement('ALTER TABLE audio_library MODIFY freesound_id INT UNSIGNED NULL');

        Schema::table('audio_library', function (Blueprint $table) {
            if (!Schema::hasColumn('audio_library', 'source')) {
                $table->string('source', 50)->nullable()->after('freesound_username');
            }
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('audio_library', function (Blueprint $table) {
            if (Schema::hasColumn('audio_library', 'source')) {
                $table->dropColumn('source');
            }
        });

        DB::statement('ALTER TABLE audio_library MODIFY freesound_id INT UNSIGNED NOT NULL');
    }
};
