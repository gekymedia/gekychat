<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * AUTO-REPLY: Create auto-reply rules table
     * Users can create rules that automatically reply to messages containing keywords
     */
    public function up(): void
    {
        Schema::create('auto_reply_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Owner of the rule
            $table->string('keyword'); // Keyword to match (case-insensitive)
            $table->text('reply_text'); // Text to reply with
            $table->integer('delay_seconds')->nullable(); // Optional delay before sending
            $table->boolean('is_active')->default(true); // Enable/disable rule
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'is_active']); // For quick lookup of active rules per user
            $table->index('keyword'); // For keyword matching
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_reply_rules');
    }
};
