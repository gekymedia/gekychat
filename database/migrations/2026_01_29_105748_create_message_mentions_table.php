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
        // Message mentions table - tracks @mentions in both 1-on-1 and group messages
        Schema::create('message_mentions', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic relation to support both Message and GroupMessage
            $table->string('mentionable_type'); // 'App\Models\Message' or 'App\Models\GroupMessage'
            $table->unsignedBigInteger('mentionable_id');
            
            // Who was mentioned
            $table->foreignId('mentioned_user_id')->constrained('users')->onDelete('cascade');
            
            // Who mentioned them
            $table->foreignId('mentioned_by_user_id')->constrained('users')->onDelete('cascade');
            
            // Position in message (for highlighting)
            $table->integer('position_start')->nullable(); // Character position where @mention starts
            $table->integer('position_end')->nullable();   // Character position where @mention ends
            
            // Read status
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            
            // Notification tracking
            $table->boolean('notification_sent')->default(false);
            $table->timestamp('notification_sent_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['mentionable_type', 'mentionable_id'], 'idx_mentions_mentionable');
            $table->index('mentioned_user_id', 'idx_mentions_user');
            $table->index(['mentioned_user_id', 'is_read'], 'idx_mentions_user_unread');
            $table->index('mentioned_by_user_id', 'idx_mentions_by_user');
            $table->index('created_at', 'idx_mentions_created');
        });
        
        // Add mention_count to messages table
        if (!Schema::hasColumn('messages', 'mention_count')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->integer('mention_count')->default(0)->after('body');
            });
        }
        
        // Add mention_count to group_messages table
        if (!Schema::hasColumn('group_messages', 'mention_count')) {
            Schema::table('group_messages', function (Blueprint $table) {
                $table->integer('mention_count')->default(0)->after('body');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_mentions');
        
        if (Schema::hasColumn('messages', 'mention_count')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('mention_count');
            });
        }
        
        if (Schema::hasColumn('group_messages', 'mention_count')) {
            Schema::table('group_messages', function (Blueprint $table) {
                $table->dropColumn('mention_count');
            });
        }
    }
};
