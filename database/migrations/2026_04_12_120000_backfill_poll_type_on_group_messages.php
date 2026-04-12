<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Group polls created before type was set on insert had NULL type; MessageResource
     * and websocket payloads skipped poll_data, so clients rendered the question as plain text.
     */
    public function up(): void
    {
        if (! Schema::hasTable('group_messages') || ! Schema::hasTable('message_polls')) {
            return;
        }

        $ids = DB::table('message_polls')
            ->whereNotNull('group_message_id')
            ->pluck('group_message_id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('group_messages')
            ->whereIn('id', $ids)
            ->where(function ($q) {
                $q->whereNull('type')->orWhere('type', '');
            })
            ->update(['type' => 'poll']);
    }

    public function down(): void
    {
        // Not reversible without knowing prior type values.
    }
};
