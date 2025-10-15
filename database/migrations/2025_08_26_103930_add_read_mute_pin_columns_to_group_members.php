<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (!Schema::hasColumn('group_members', 'last_read_message_id')) {
                $table->unsignedBigInteger('last_read_message_id')->nullable()->after('role');
                $table->foreign('last_read_message_id')->references('id')->on('group_messages')->nullOnDelete();
            }
            if (!Schema::hasColumn('group_members', 'muted_until')) {
                $table->timestamp('muted_until')->nullable()->after('last_read_message_id');
            }
            if (!Schema::hasColumn('group_members', 'pinned_at')) {
                $table->timestamp('pinned_at')->nullable()->after('muted_until');
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (Schema::hasColumn('group_members', 'last_read_message_id')) {
                $table->dropForeign(['last_read_message_id']);
                $table->dropColumn('last_read_message_id');
            }
            if (Schema::hasColumn('group_members', 'muted_until')) {
                $table->dropColumn('muted_until');
            }
            if (Schema::hasColumn('group_members', 'pinned_at')) {
                $table->dropColumn('pinned_at');
            }
        });
    }
};
