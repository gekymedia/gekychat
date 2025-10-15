<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_statuses', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');

            // sent | delivered | read
            $table->string('status', 16)->default('sent');

            // per-user "delete for me"
            $table->timestamp('deleted_at')->nullable();

            $table->timestamps();

            $table->unique(['message_id', 'user_id'], 'msg_status_unique');

            $table->foreign('message_id')
                  ->references('id')->on('messages')
                  ->cascadeOnDelete();

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->cascadeOnDelete();
        });

        // OPTIONAL: backfill from messages.deleted_for_user_id if it exists
        if (Schema::hasColumn('messages', 'deleted_for_user_id')) {
            DB::statement("
                INSERT INTO message_statuses (message_id, user_id, status, deleted_at, created_at, updated_at)
                SELECT m.id, m.deleted_for_user_id, 'sent', NOW(), NOW(), NOW()
                FROM messages m
                WHERE m.deleted_for_user_id IS NOT NULL
                ON DUPLICATE KEY UPDATE deleted_at = COALESCE(message_statuses.deleted_at, VALUES(deleted_at))
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_statuses');
    }
};
