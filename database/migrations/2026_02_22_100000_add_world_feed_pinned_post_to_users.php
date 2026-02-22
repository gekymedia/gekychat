<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('world_feed_pinned_post_id')->nullable()->after('username_changed_at');
            $table->foreign('world_feed_pinned_post_id')
                ->references('id')
                ->on('world_feed_posts')
                ->onDelete('set null');
            $table->index('world_feed_pinned_post_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['world_feed_pinned_post_id']);
            $table->dropColumn('world_feed_pinned_post_id');
        });
    }
};
