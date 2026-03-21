<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_session_id')->constrained('call_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1–5 stars
            $table->json('issues')->nullable(); // e.g. ["echo","video_lag","dropped","audio_cut"]
            $table->string('comment', 2000)->nullable();
            $table->string('call_type', 16)->nullable(); // voice|video snapshot at submit
            $table->unsignedInteger('duration_seconds')->nullable(); // optional client-reported duration
            $table->json('client_meta')->nullable(); // platform, app_version
            $table->timestamps();

            $table->unique(['call_session_id', 'user_id']);
            $table->index('rating');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_ratings');
    }
};
