<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audio_usage_stats', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('audio_library_id');
            
            // Time-based tracking
            $table->date('date');
            $table->unsignedTinyInteger('hour')->nullable(); // 0-23, NULL for daily aggregates
            
            // Metrics
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('unique_users')->default(0);
            $table->unsignedInteger('total_plays')->default(0);
            
            $table->timestamps();
            
            // Foreign key
            $table->foreign('audio_library_id')->references('id')->on('audio_library')->onDelete('cascade');
            
            // Unique constraint
            $table->unique(['audio_library_id', 'date', 'hour']);
            $table->index(['date', 'usage_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audio_usage_stats');
    }
};
