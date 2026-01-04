<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('conversation_user', function (Blueprint $table) {
            if (!Schema::hasColumn('conversation_user', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('pinned_at');
                $table->index('archived_at');
            }
        });
    }

    public function down(): void {
        Schema::table('conversation_user', function (Blueprint $table) {
            if (Schema::hasColumn('conversation_user', 'archived_at')) {
                $table->dropIndex(['archived_at']);
                $table->dropColumn('archived_at');
            }
        });
    }
};

