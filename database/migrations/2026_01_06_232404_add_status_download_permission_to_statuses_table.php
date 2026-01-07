<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Run the migrations.
     * PHASE 0: Foundation migration - adds allow_download column for Phase 1 implementation
     */
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            // TODO (PHASE 1): Wire this into StatusController to check permission before allowing downloads
            $table->boolean('allow_download')->default(true)->after('view_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            $table->dropColumn('allow_download');
        });
    }
};
