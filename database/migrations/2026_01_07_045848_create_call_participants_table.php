<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * PHASE 2: Create call participants table
     * 
     * Tracks participants in group calls and meetings.
     * Supports join/leave without ending the call.
     */
    public function up(): void
    {
        Schema::create('call_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('call_session_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('status', ['invited', 'joined', 'left', 'declined'])->default('invited');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->boolean('is_host')->default(false); // Meeting host (anchor)
            $table->timestamps();

            $table->foreign('call_session_id')->references('id')->on('call_sessions')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['call_session_id', 'user_id']);
            $table->index('call_session_id');
            $table->index('status');
        });

        // Add meeting_mode and invite_token to call_sessions
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->boolean('is_meeting')->default(false)->after('status'); // Meeting-style call
            $table->string('invite_token')->unique()->nullable()->after('is_meeting'); // For invite links
            $table->unsignedBigInteger('host_id')->nullable()->after('invite_token'); // Meeting host
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_participants');
        
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->dropColumn(['is_meeting', 'invite_token', 'host_id']);
        });
    }
};
