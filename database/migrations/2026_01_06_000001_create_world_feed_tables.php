<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // World search history (check if table exists first)
        if (!Schema::hasTable('world_search_history')) {
            Schema::create('world_search_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('query');
                $table->timestamp('created_at');
                
                $table->index(['user_id', 'created_at']);
                $table->index('query');
            });
        }

        // World search clicks (check if table exists first)
        if (!Schema::hasTable('world_search_clicks')) {
            Schema::create('world_search_clicks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('query');
                $table->enum('clicked_type', ['user', 'video', 'hashtag', 'post']);
                $table->unsignedBigInteger('clicked_id');
                $table->timestamp('created_at');
                
                $table->index('query');
                $table->index(['user_id', 'query']);
            });
        }

        // World comment likes (check if table exists first)
        // Note: world_feed_comments table is created in a later migration (2026_01_06_235454)
        // So we need to create the table first, then add the foreign key later if the referenced table exists
        if (!Schema::hasTable('world_comment_likes')) {
            Schema::create('world_comment_likes', function (Blueprint $table) {
                $table->id();
                // Create column without foreign key first
                $table->unsignedBigInteger('comment_id');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->timestamp('created_at');
                
                $table->unique(['comment_id', 'user_id']);
                $table->index('comment_id');
            });
            
            // Add foreign key only if world_feed_comments table exists
            if (Schema::hasTable('world_feed_comments')) {
                Schema::table('world_comment_likes', function (Blueprint $table) {
                    $table->foreign('comment_id')->references('id')->on('world_feed_comments')->onDelete('cascade');
                });
            }
        } else {
            // Table exists, but foreign key might not - add it if world_feed_comments exists
            if (Schema::hasTable('world_feed_comments')) {
                // Check if foreign key already exists
                $foreignKeys = \DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'world_comment_likes' 
                    AND COLUMN_NAME = 'comment_id' 
                    AND REFERENCED_TABLE_NAME = 'world_feed_comments'
                ");
                
                if (empty($foreignKeys)) {
                    Schema::table('world_comment_likes', function (Blueprint $table) {
                        $table->foreign('comment_id')->references('id')->on('world_feed_comments')->onDelete('cascade');
                    });
                }
            }
        }

        // Add parent_id to world_comments for threaded replies
        if (Schema::hasTable('world_comments')) {
            Schema::table('world_comments', function (Blueprint $table) {
                if (!Schema::hasColumn('world_comments', 'parent_id')) {
                    $table->foreignId('parent_id')->nullable()->constrained('world_comments')->onDelete('cascade');
                    $table->index('parent_id');
                }
            });
        }

        // World hashtags
        if (!Schema::hasTable('world_hashtags')) {
            Schema::create('world_hashtags', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->integer('posts_count')->default(0);
                $table->timestamps();
                
                $table->index('name');
                $table->index('posts_count');
            });
        }

        // Follows table
        if (!Schema::hasTable('follows')) {
            Schema::create('follows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('follower_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('following_id')->constrained('users')->onDelete('cascade');
                $table->timestamp('created_at');
                
                $table->unique(['follower_id', 'following_id']);
                $table->index('follower_id');
                $table->index('following_id');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('world_search_clicks');
        Schema::dropIfExists('world_search_history');
        Schema::dropIfExists('world_comment_likes');
        Schema::dropIfExists('follows');
        Schema::dropIfExists('world_hashtags');
        
        if (Schema::hasTable('world_comments')) {
            Schema::table('world_comments', function (Blueprint $table) {
                if (Schema::hasColumn('world_comments', 'parent_id')) {
                    $table->dropForeign(['parent_id']);
                    $table->dropColumn('parent_id');
                }
            });
        }
    }
};
