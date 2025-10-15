<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_statuses', function (Blueprint $t) {
            $t->index(['user_id', 'status'], 'msg_status_user_status_idx');
            $t->index(['user_id', 'deleted_at'], 'msg_status_user_deleted_idx');
            $t->index(['message_id', 'status'], 'msg_status_message_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('message_statuses', function (Blueprint $t) {
            $t->dropIndex('msg_status_user_status_idx');
            $t->dropIndex('msg_status_user_deleted_idx');
            $t->dropIndex('msg_status_message_status_idx');
        });
    }
};
