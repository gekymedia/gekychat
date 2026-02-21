<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add duet/stitch support to world feed posts (TikTok-style).
     * - Duet: user's video plays side-by-side with original.
     * - Stitch: user's video plays after a segment of the original.
     */
    public function up(): void
    {
        Schema::table('world_feed_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('original_post_id')->nullable()->after('creator_id');
            $table->string('post_type', 20)->default('original')->after('original_post_id'); // 'original' | 'duet' | 'stitch'
            $table->unsignedInteger('stitch_start_ms')->nullable()->after('post_type'); // For stitch: start of segment (ms)
            $table->unsignedInteger('stitch_end_ms')->nullable()->after('stitch_start_ms');   // For stitch: end of segment (ms)
        });

        Schema::table('world_feed_posts', function (Blueprint $table) {
            $table->foreign('original_post_id')->references('id')->on('world_feed_posts')->onDelete('set null');
            $table->index('original_post_id');
            $table->index('post_type');
        });
    }

    public function down(): void
    {
        Schema::table('world_feed_posts', function (Blueprint $table) {
            $table->dropForeign(['original_post_id']);
            $table->dropIndex(['original_post_id']);
            $table->dropIndex(['post_type']);
        });
        Schema::table('world_feed_posts', function (Blueprint $table) {
            $table->dropColumn(['original_post_id', 'post_type', 'stitch_start_ms', 'stitch_end_ms']);
        });
    }
};
