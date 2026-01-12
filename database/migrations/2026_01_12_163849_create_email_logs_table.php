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
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('from_email')->index();
            $table->string('from_name')->nullable();
            $table->json('to_emails'); // Array of recipient emails
            $table->string('subject')->nullable();
            $table->string('message_id_header')->nullable()->unique();
            $table->enum('status', ['success', 'failed', 'ignored'])->default('success');
            $table->string('routed_to_username')->nullable()->index(); // Username extracted from email
            $table->unsignedBigInteger('routed_to_user_id')->nullable()->index(); // User ID if found
            $table->unsignedBigInteger('conversation_id')->nullable()->index(); // Conversation created
            $table->unsignedBigInteger('message_id')->nullable()->index(); // Message created
            $table->text('failure_reason')->nullable(); // Why it failed
            $table->text('error_details')->nullable(); // Additional error info
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->foreign('routed_to_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('set null');
            $table->foreign('message_id')->references('id')->on('messages')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
