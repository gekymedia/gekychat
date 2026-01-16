<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_api_keys', function (Blueprint $table) {
            if (!Schema::hasColumn('user_api_keys', 'webhook_url')) {
                $table->string('webhook_url')->nullable()->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_api_keys', function (Blueprint $table) {
            if (Schema::hasColumn('user_api_keys', 'webhook_url')) {
                $table->dropColumn('webhook_url');
            }
        });
    }
};
