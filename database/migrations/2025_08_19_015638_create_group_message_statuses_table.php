<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_message_statuses', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('group_message_id');
            $table->unsignedBigInteger('user_id');

            // sent | delivered | read
            $table->string('status', 16)->default('sent');

            // per-user "delete for me"
            $table->timestamp('deleted_at')->nullable();

            $table->timestamps();

            $table->unique(['group_message_id', 'user_id'], 'grp_msg_status_unique');

            $table->foreign('group_message_id')
                  ->references('id')->on('group_messages')
                  ->cascadeOnDelete();

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->cascadeOnDelete();
        });

        // OPTIONAL backfill if you previously had group_messages.deleted_for_user_id
        if (Schema::hasColumn('group_messages', 'deleted_for_user_id')) {
            DB::statement("
                INSERT INTO group_message_statuses (group_message_id, user_id, status, deleted_at, created_at, updated_at)
                SELECT gm.id, gm.deleted_for_user_id, 'sent', NOW(), NOW(), NOW()
                FROM group_messages gm
                WHERE gm.deleted_for_user_id IS NOT NULL
                ON DUPLICATE KEY UPDATE deleted_at = COALESCE(group_message_statuses.deleted_at, VALUES(deleted_at))
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('group_message_statuses');
    }
};
