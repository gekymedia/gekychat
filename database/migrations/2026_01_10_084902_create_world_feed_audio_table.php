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
        Schema::create('world_feed_audio', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->unsignedBigInteger('world_feed_post_id');
            $table->unsignedBigInteger('audio_library_id');
            
            // Playback settings
            $table->unsignedTinyInteger('volume_level')->default(100); // 0-100
            $table->decimal('audio_start_time', 8, 2)->default(0); // Start offset in audio
            $table->boolean('loop_audio')->default(true);
            $table->decimal('fade_in_duration', 4, 2)->default(0); // seconds
            $table->decimal('fade_out_duration', 4, 2)->default(0);
            
            // Attribution (snapshot at time of use)
            $table->json('license_snapshot'); // License details when attached
            $table->text('attribution_displayed')->nullable();
            
            // Metadata
            $table->unsignedBigInteger('attached_by');
            $table->timestamp('attached_at')->useCurrent();
            
            // Foreign keys
            $table->foreign('world_feed_post_id')->references('id')->on('world_feed_posts')->onDelete('cascade');
            $table->foreign('audio_library_id')->references('id')->on('audio_library')->onDelete('restrict');
            $table->foreign('attached_by')->references('id')->on('users')->onDelete('cascade');
            
            // Unique constraint - one audio per post
            $table->unique('world_feed_post_id');
            $table->index('audio_library_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('world_feed_audio');
    }
};
