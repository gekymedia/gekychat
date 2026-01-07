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
     * PHASE 0: Foundation migration - adds stealth_view column for Phase 1 stealth status viewing
     */
    public function up(): void
    {
        Schema::table('status_views', function (Blueprint $table) {
            // TODO (PHASE 1): When user enables stealth mode, set this to true when creating StatusView
            // TODO (PHASE 1): Filter stealth views from status owner's viewer list
            $table->boolean('stealth_view')->default(false)->after('user_id');
            $table->index('stealth_view');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('status_views', function (Blueprint $table) {
            $table->dropIndex(['stealth_view']);
            $table->dropColumn('stealth_view');
        });
    }
};
