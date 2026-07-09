<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issue_reports', function (Blueprint $table) {
            $table->text('admin_notes')->nullable()->after('status');
            $table->text('admin_reply')->nullable()->after('admin_notes');
            $table->timestamp('admin_reply_at')->nullable()->after('admin_reply');
            $table->foreignId('replied_by_user_id')->nullable()->after('admin_reply_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('issue_reports', function (Blueprint $table) {
            $table->dropForeign(['replied_by_user_id']);
            $table->dropColumn(['admin_notes', 'admin_reply', 'admin_reply_at', 'replied_by_user_id']);
        });
    }
};
