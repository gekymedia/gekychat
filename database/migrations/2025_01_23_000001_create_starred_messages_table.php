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
        Schema::create('starred_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('message_id')->nullable(); // For regular messages
            $table->unsignedBigInteger('group_message_id')->nullable(); // For group messages
            $table->timestamp('starred_at')->useCurrent();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('message_id')->references('id')->on('messages')->cascadeOnDelete();
            $table->foreign('group_message_id')->references('id')->on('group_messages')->cascadeOnDelete();
            
            // Ensure a user can only star a message once
            $table->unique(['user_id', 'message_id'], 'user_message_star_unique');
            $table->unique(['user_id', 'group_message_id'], 'user_group_message_star_unique');
            
            $table->index('user_id');
            $table->index(['message_id', 'group_message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('starred_messages');
    }
};

