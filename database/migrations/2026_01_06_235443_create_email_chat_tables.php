<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * PHASE 2: Create email chat tables
     * 
     * Email-Chat allows emails to be received and sent as chat threads inside GekyChat.
     * Emails map to conversations, with email metadata stored separately.
     */
    public function up(): void
    {
        // Email threads (maps emails to conversations)
        Schema::create('email_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id'); // Maps to conversations table
            $table->string('thread_id')->unique(); // Email thread ID (Message-ID header chain)
            $table->string('subject')->nullable();
            $table->json('participants'); // {from: {...}, to: [...], cc: [...], bcc: [...]}
            $table->timestamp('last_email_at')->nullable();
            $table->integer('email_count')->default(0);
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->index('thread_id');
            $table->index('conversation_id');
        });

        // Email messages (links emails to messages in conversations)
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id'); // References messages table
            $table->unsignedBigInteger('email_thread_id'); // References email_threads
            $table->string('message_id_header')->unique(); // Email Message-ID header
            $table->string('in_reply_to')->nullable(); // In-Reply-To header
            $table->string('references')->nullable(); // References header
            $table->json('from_email'); // {name, address}
            $table->json('to_emails'); // [{name, address}, ...]
            $table->json('cc_emails')->nullable();
            $table->json('bcc_emails')->nullable();
            $table->text('html_body')->nullable();
            $table->text('text_body')->nullable();
            $table->string('status')->default('sent'); // 'sent', 'delivered', 'failed', 'bounced'
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('messages')->onDelete('cascade');
            $table->foreign('email_thread_id')->references('id')->on('email_threads')->onDelete('cascade');
            $table->index('message_id_header');
            $table->index('email_thread_id');
        });

        // Email-to-user mapping (resolves email addresses to users)
        Schema::create('email_user_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->unsignedBigInteger('user_id'); // Maps email to user
            $table->boolean('is_primary')->default(false); // Primary email for user
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_user_mappings');
        Schema::dropIfExists('email_messages');
        Schema::dropIfExists('email_threads');
    }
};
