<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{public function up(): void
{
    Schema::table('group_message_statuses', function (Blueprint $t) {
        $t->index(['user_id', 'status']);               // filters by my row + read/delivered
        $t->index(['user_id', 'deleted_at']);           // visibility checks
        $t->index(['group_message_id', 'status']);      // per-message counters
    });
}

public function down(): void
{
    Schema::table('group_message_statuses', function (Blueprint $t) {
        $t->dropIndex(['group_message_statuses_user_id_status_index']);
        $t->dropIndex(['group_message_statuses_user_id_deleted_at_index']);
        $t->dropIndex(['group_message_statuses_group_message_id_status_index']);
    });
}

};
