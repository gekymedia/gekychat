<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_feed_posts', function (Blueprint $table) {
            $table->string('media_url_watermarked', 512)->nullable()->after('media_url');
        });
    }

    public function down(): void
    {
        Schema::table('world_feed_posts', function (Blueprint $table) {
            $table->dropColumn('media_url_watermarked');
        });
    }
};
