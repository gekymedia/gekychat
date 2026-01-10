<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Creates user upload limits table for per-user overrides
     */
    public function up(): void
    {
        Schema::create('user_upload_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->integer('world_feed_max_duration')->nullable(); // Override for World Feed duration (seconds)
            $table->integer('chat_video_max_size')->nullable(); // Override for chat video size (bytes)
            $table->integer('status_max_duration')->nullable(); // Override for Status duration (seconds)
            $table->text('notes')->nullable(); // Admin notes about why this override exists
            $table->unsignedBigInteger('set_by_admin_id')->nullable(); // Which admin set this override
            $table->timestamps();
            $table->softDeletes(); // Allow soft deletion of overrides

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('set_by_admin_id')->references('id')->on('users')->onDelete('set null');
            
            $table->unique('user_id'); // One override per user
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_upload_limits');
    }
};
