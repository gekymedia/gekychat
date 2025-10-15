<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('conversation_user')) {
            Schema::create('conversation_user', function (Blueprint $table) {
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('user_id');
                $table->enum('role', ['owner','admin','member'])->default('member');

                // WhatsApp-grade extras
                $table->unsignedBigInteger('last_read_message_id')->nullable();
                $table->timestamp('muted_until')->nullable();
                $table->timestamp('pinned_at')->nullable();

                $table->primary(['conversation_id','user_id']);
                $table->index('pinned_at');

                $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('conversation_user');
    }
};
