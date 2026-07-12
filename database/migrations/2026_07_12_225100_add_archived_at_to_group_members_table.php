<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (!Schema::hasColumn('group_members', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('pinned_at');
                $table->index(['user_id', 'archived_at'], 'idx_group_members_user_archived');
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (Schema::hasColumn('group_members', 'archived_at')) {
                $table->dropIndex(['user_id', 'archived_at']);
                $table->dropColumn('archived_at');
            }
        });
    }
};
