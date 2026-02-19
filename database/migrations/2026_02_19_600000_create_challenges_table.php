<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('challenges')) {
            Schema::create('challenges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->string('hashtag')->unique(); // e.g. #IceBucketChallenge
                $table->text('description')->nullable();
                $table->string('cover_url')->nullable();
                $table->string('audio_url')->nullable();   // optional backing track
                $table->string('status')->default('active'); // active | ended | featured
                $table->unsignedBigInteger('participants_count')->default(0);
                $table->unsignedBigInteger('posts_count')->default(0);
                $table->unsignedBigInteger('views_count')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'participants_count']);
                $table->index('created_at');
            });
        }

        // Challenge participations (user joined / submitted)
        if (!Schema::hasTable('challenge_participations')) {
            Schema::create('challenge_participations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('world_post_id')->nullable()->constrained('world_posts')->nullOnDelete();
                $table->timestamps();

                $table->unique(['challenge_id', 'user_id']);
                $table->index('challenge_id');
            });
        }

        // Add challenge_id FK to world_posts for linking posts to challenges
        if (Schema::hasTable('world_posts') && !Schema::hasColumn('world_posts', 'challenge_id')) {
            Schema::table('world_posts', function (Blueprint $table) {
                $table->foreignId('challenge_id')->nullable()->constrained()->nullOnDelete()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('world_posts') && Schema::hasColumn('world_posts', 'challenge_id')) {
            Schema::table('world_posts', function (Blueprint $table) {
                $table->dropForeign(['challenge_id']);
                $table->dropColumn('challenge_id');
            });
        }
        Schema::dropIfExists('challenge_participations');
        Schema::dropIfExists('challenges');
    }
};
