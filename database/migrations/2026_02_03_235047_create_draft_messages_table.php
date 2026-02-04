<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates draft_messages table for WhatsApp-style draft auto-save functionality.
     * Drafts are stored per conversation per user, allowing seamless multi-device sync.
     */
    public function up(): void
    {
        Schema::create('draft_messages', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->onDelete('cascade');
            
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            
            // Draft content
            $table->text('content')->nullable();
            
            // Media attachments (JSON array of file paths/URLs)
            $table->text('media_urls_json')->nullable();
            
            // Reply context
            $table->foreignId('reply_to_id')
                ->nullable()
                ->constrained('messages')
                ->onDelete('set null');
            
            // Mentions (JSON array of user IDs)
            $table->text('mentions_json')->nullable();
            
            // Timestamps
            $table->timestamp('saved_at')->useCurrent();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['conversation_id', 'user_id']);
            $table->index('saved_at');
            
            // Unique constraint: one draft per conversation per user
            $table->unique(['conversation_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_messages');
    }
};
