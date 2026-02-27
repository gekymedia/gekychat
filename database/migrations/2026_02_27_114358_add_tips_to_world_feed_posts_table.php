<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_feed_posts', function (Blueprint $table) {
            $table->unsignedInteger('tips_count')->default(0)->after('shares_count');
            $table->unsignedBigInteger('tips_total')->default(0)->after('tips_count');
        });
    }

    public function down(): void
    {
        Schema::table('world_feed_posts', function (Blueprint $table) {
            $table->dropColumn(['tips_count', 'tips_total']);
        });
    }
};
