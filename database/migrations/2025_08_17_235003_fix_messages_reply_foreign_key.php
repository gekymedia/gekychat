<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_messages', function (Blueprint $table) {
            // Add only if missing to keep this migration idempotent
            if (!Schema::hasColumn('group_messages', 'forwarded_from_id')) {
                $table->unsignedBigInteger('forwarded_from_id')->nullable()->after('reply_to_id');
            }
            if (!Schema::hasColumn('group_messages', 'forward_chain')) {
                $table->json('forward_chain')->nullable()->after('forwarded_from_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_messages', function (Blueprint $table) {
            if (Schema::hasColumn('group_messages', 'forward_chain')) {
                $table->dropColumn('forward_chain');
            }
            if (Schema::hasColumn('group_messages', 'forwarded_from_id')) {
                // If you later add a foreign key, drop it before dropping the column.
                $table->dropColumn('forwarded_from_id');
            }
        });
    }
};
