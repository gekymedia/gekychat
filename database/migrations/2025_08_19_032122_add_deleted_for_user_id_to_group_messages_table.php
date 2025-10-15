<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('group_messages', 'deleted_for_user_id')) {
                $table->foreignId('deleted_for_user_id')->nullable()->after('edited_at')
                      ->constrained('users')->nullOnDelete()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_messages', function (Blueprint $table) {
            if (Schema::hasColumn('group_messages', 'deleted_for_user_id')) {
                try { $table->dropForeign(['deleted_for_user_id']); } catch (\Throwable $e) {}
                $table->dropColumn('deleted_for_user_id');
            }
        });
    }
};
