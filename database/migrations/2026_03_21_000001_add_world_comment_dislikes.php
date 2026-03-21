<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_feed_comments', function (Blueprint $table) {
            if (! Schema::hasColumn('world_feed_comments', 'dislikes_count')) {
                $table->unsignedInteger('dislikes_count')->default(0);
            }
        });

        if (! Schema::hasTable('world_comment_dislikes')) {
            Schema::create('world_comment_dislikes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('comment_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();

                $table->unique(['comment_id', 'user_id']);
                $table->foreign('comment_id')->references('id')->on('world_feed_comments')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('world_comment_dislikes');
        Schema::table('world_feed_comments', function (Blueprint $table) {
            if (Schema::hasColumn('world_feed_comments', 'dislikes_count')) {
                $table->dropColumn('dislikes_count');
            }
        });
    }
};
