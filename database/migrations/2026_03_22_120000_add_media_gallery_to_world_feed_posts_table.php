<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_feed_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('world_feed_posts', 'media_gallery')) {
                $table->json('media_gallery')->nullable()->after('media_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('world_feed_posts', function (Blueprint $table) {
            if (Schema::hasColumn('world_feed_posts', 'media_gallery')) {
                $table->dropColumn('media_gallery');
            }
        });
    }
};
