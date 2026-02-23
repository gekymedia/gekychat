<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_messages') && !Schema::hasColumn('group_messages', 'expires_at')) {
            Schema::table('group_messages', function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->after('is_view_once');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('group_messages', 'expires_at')) {
            Schema::table('group_messages', function (Blueprint $table) {
                $table->dropColumn('expires_at');
            });
        }
    }
};
