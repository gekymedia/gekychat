<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 TikTok-style playback: keep original, serve compressed faststart MP4s.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_feed_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('world_feed_posts', 'media_url_original')) {
                $table->string('media_url_original', 512)->nullable()->after('media_url_watermarked');
            }
            if (! Schema::hasColumn('world_feed_posts', 'media_url_480')) {
                $table->string('media_url_480', 512)->nullable()->after('media_url_original');
            }
            if (! Schema::hasColumn('world_feed_posts', 'video_processing_status')) {
                $table->string('video_processing_status', 32)->nullable()->after('media_url_480');
            }
        });
    }

    public function down(): void
    {
        Schema::table('world_feed_posts', function (Blueprint $table) {
            $cols = [];
            foreach (['media_url_original', 'media_url_480', 'video_processing_status'] as $col) {
                if (Schema::hasColumn('world_feed_posts', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
