<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * PHASE 2: Create world feed tables
     * 
     * World feed is a public discovery feed similar to TikTok - short-form videos and posts.
     */
    public function up(): void
    {
        // World feed posts (public short-form content)
        Schema::create('world_feed_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('creator_id'); // User who created the post
            $table->string('type')->default('video'); // 'video', 'image', 'text'
            $table->text('caption')->nullable();
            $table->string('media_url')->nullable(); // Video or image URL
            $table->string('thumbnail_url')->nullable();
            $table->integer('duration')->nullable(); // Video duration in seconds
            $table->integer('likes_count')->default(0);
            $table->integer('comments_count')->default(0);
            $table->integer('views_count')->default(0);
            $table->integer('shares_count')->default(0);
            $table->boolean('is_public')->default(true);
            $table->json('tags')->nullable(); // Hashtags
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('creator_id');
            $table->index('created_at');
            $table->index('is_public');
        });

        // World feed likes/reactions
        Schema::create('world_feed_likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['post_id', 'user_id']); // One like per user per post
            $table->foreign('post_id')->references('id')->on('world_feed_posts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('post_id');
        });

        // World feed comments
        Schema::create('world_feed_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('user_id');
            $table->text('comment');
            $table->unsignedBigInteger('parent_id')->nullable(); // For nested replies
            $table->integer('likes_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('post_id')->references('id')->on('world_feed_posts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('world_feed_comments')->onDelete('cascade');
            $table->index('post_id');
            $table->index('parent_id');
        });

        // World feed views (for view counts)
        Schema::create('world_feed_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('user_id')->nullable(); // Nullable for anonymous views
            $table->timestamp('viewed_at')->useCurrent();

            $table->foreign('post_id')->references('id')->on('world_feed_posts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('post_id');
        });

        // Creator follows (for following creators in world feed)
        Schema::create('world_feed_follows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('follower_id'); // User following
            $table->unsignedBigInteger('creator_id'); // Creator being followed
            $table->timestamp('followed_at')->useCurrent();

            $table->unique(['follower_id', 'creator_id']); // One follow per user per creator
            $table->foreign('follower_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('follower_id');
            $table->index('creator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('world_feed_follows');
        Schema::dropIfExists('world_feed_views');
        Schema::dropIfExists('world_feed_comments');
        Schema::dropIfExists('world_feed_likes');
        Schema::dropIfExists('world_feed_posts');
    }
};
