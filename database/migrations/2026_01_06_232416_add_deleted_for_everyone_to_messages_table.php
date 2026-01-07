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
     * PHASE 0: Foundation migration - adds deleted_for_everyone_at column for Phase 1 "delete for everyone" feature
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // TODO (PHASE 1): Set this timestamp when user selects "delete for everyone" (within time limit)
            // TODO (PHASE 1): Hard delete or hide message for all participants when this is set
            // TODO (PHASE 1): Exception: Preserve in "saved messages" conversations
            $table->timestamp('deleted_for_everyone_at')->nullable()->after('expires_at');
            $table->index('deleted_for_everyone_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['deleted_for_everyone_at']);
            $table->dropColumn('deleted_for_everyone_at');
        });
    }
};
