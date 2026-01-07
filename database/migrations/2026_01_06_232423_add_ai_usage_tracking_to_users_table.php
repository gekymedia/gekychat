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
     * PHASE 0: Foundation migration - adds AI usage tracking columns for Phase 1+ AI improvements
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // TODO (PHASE 2): Track AI usage for rate limiting and premium gating
            // TODO (PHASE 2): Reset ai_usage_count daily/monthly based on plan
            // TODO (PHASE 2): Check ai_usage_count before allowing AI requests in BotService
            $table->integer('ai_usage_count')->default(0)->after('settings');
            $table->timestamp('ai_last_used_at')->nullable()->after('ai_usage_count');
            $table->timestamp('ai_usage_reset_at')->nullable()->after('ai_last_used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ai_usage_count', 'ai_last_used_at', 'ai_usage_reset_at']);
        });
    }
};
