<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'reply_to_attachment_id')) {
                $table->unsignedBigInteger('reply_to_attachment_id')->nullable()->after('reply_to');
            }
        });

        Schema::table('group_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('group_messages', 'reply_to_attachment_id')) {
                $table->unsignedBigInteger('reply_to_attachment_id')->nullable()->after('reply_to');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'reply_to_attachment_id')) {
                $table->dropColumn('reply_to_attachment_id');
            }
        });

        Schema::table('group_messages', function (Blueprint $table) {
            if (Schema::hasColumn('group_messages', 'reply_to_attachment_id')) {
                $table->dropColumn('reply_to_attachment_id');
            }
        });
    }
};
