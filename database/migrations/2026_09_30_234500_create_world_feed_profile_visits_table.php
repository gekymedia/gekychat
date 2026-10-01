<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('world_feed_profile_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            // Optional: post the visitor was watching before opening the profile.
            $table->unsignedBigInteger('source_post_id')->nullable();
            $table->timestamp('visited_at');
            $table->timestamps();

            $table->index(['visitor_id', 'visited_at']);
            $table->index(['creator_id', 'visited_at']);
            $table->index(['visitor_id', 'creator_id', 'visited_at']);
            $table->foreign('source_post_id')
                ->references('id')
                ->on('world_feed_posts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('world_feed_profile_visits');
    }
};
