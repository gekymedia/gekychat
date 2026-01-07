<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * PHASE 2: Create live broadcast tables
     * 
     * Live broadcasting allows users to stream live video with viewer chat.
     */
    public function up(): void
    {
        // Live broadcast sessions
        Schema::create('live_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('broadcaster_id'); // User who is broadcasting
            $table->string('title')->nullable();
            $table->string('status')->default('scheduled'); // 'scheduled', 'live', 'ended'
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('viewers_count')->default(0);
            $table->string('stream_key')->unique(); // Unique stream key for WebRTC/RTMP
            $table->string('room_name')->unique(); // LiveKit room name
            $table->boolean('save_replay')->default(false);
            $table->string('replay_url')->nullable(); // URL to saved replay video
            $table->timestamps();

            $table->foreign('broadcaster_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('status');
            $table->index('broadcaster_id');
        });

        // Live broadcast viewers
        Schema::create('live_broadcast_viewers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('broadcast_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();

            $table->foreign('broadcast_id')->references('id')->on('live_broadcasts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['broadcast_id', 'user_id']);
        });

        // Live broadcast chat messages
        Schema::create('live_broadcast_chat', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('broadcast_id');
            $table->unsignedBigInteger('user_id');
            $table->text('message');
            $table->timestamps();

            $table->foreign('broadcast_id')->references('id')->on('live_broadcasts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('broadcast_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_broadcast_chat');
        Schema::dropIfExists('live_broadcast_viewers');
        Schema::dropIfExists('live_broadcasts');
    }
};
