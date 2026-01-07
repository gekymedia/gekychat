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
     * AUTO-REPLY: Create auto-reply cooldowns table
     * Anti-loop protection: Track last auto-reply per conversation
     * Default cooldown: 24 hours per conversation
     */
    public function up(): void
    {
        Schema::create('auto_reply_cooldowns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id'); // Conversation where auto-reply was sent
            $table->unsignedBigInteger('rule_id'); // Which rule was triggered
            $table->timestamp('last_auto_reply_at'); // When last auto-reply was sent
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('rule_id')->references('id')->on('auto_reply_rules')->onDelete('cascade');
            
            // Unique constraint: one cooldown record per conversation per rule
            $table->unique(['conversation_id', 'rule_id']);
            
            // Index for cooldown checks
            $table->index(['conversation_id', 'last_auto_reply_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_reply_cooldowns');
    }
};
