<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-status audience (optional). When null, visibility uses the owner's global StatusPrivacySetting.
     */
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            $table->string('privacy', 32)->nullable();
            $table->json('excluded_user_ids')->nullable();
            $table->json('included_user_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            $table->dropColumn(['privacy', 'excluded_user_ids', 'included_user_ids']);
        });
    }
};
