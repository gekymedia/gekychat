<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * PHASE 2: Create channels and channel posts tables
     * 
     * Channels are one-way broadcasts. We reuse the groups table with type='channel',
     * but channels have separate posts (channel_posts) and followers (channel_followers).
     */
    public function up(): void
    {
        // Channel posts (separate from group_messages - one-way broadcast)
        Schema::create('channel_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id'); // References groups table where type='channel'
            $table->unsignedBigInteger('posted_by'); // Admin who posted
            $table->string('type')->default('text'); // 'text', 'image', 'video'
            $table->text('body')->nullable();
            $table->string('media_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->integer('views_count')->default(0);
            $table->integer('reactions_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('channel_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('posted_by')->references('id')->on('users')->onDelete('cascade');
            $table->index('channel_id');
            $table->index('created_at');
        });

        // Channel followers (separate from group_members - one-way subscription)
        Schema::create('channel_followers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id'); // References groups table where type='channel'
            $table->unsignedBigInteger('user_id');
            $table->timestamp('followed_at')->useCurrent();
            $table->timestamp('muted_until')->nullable();

            $table->unique(['channel_id', 'user_id']);
            $table->foreign('channel_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
        });

        // Channel post reactions (optional reactions, no replies)
        Schema::create('channel_post_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('user_id');
            $table->string('emoji')->default('👍'); // Single emoji reaction
            $table->timestamps();

            $table->unique(['post_id', 'user_id']); // One reaction per user per post
            $table->foreign('post_id')->references('id')->on('channel_posts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('post_id');
        });

        // Channel post views (for view count)
        Schema::create('channel_post_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('viewed_at')->useCurrent();

            $table->unique(['post_id', 'user_id']); // One view per user per post
            $table->foreign('post_id')->references('id')->on('channel_posts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_post_views');
        Schema::dropIfExists('channel_post_reactions');
        Schema::dropIfExists('channel_followers');
        Schema::dropIfExists('channel_posts');
    }
};
