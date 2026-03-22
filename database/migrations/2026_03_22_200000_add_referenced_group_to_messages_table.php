<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('referenced_group_id')->nullable()->after('referenced_status_id');
            $table->unsignedBigInteger('referenced_group_message_id')->nullable()->after('referenced_group_id');
            $table->foreign('referenced_group_id')
                ->references('id')
                ->on('groups')
                ->nullOnDelete();
            $table->foreign('referenced_group_message_id')
                ->references('id')
                ->on('group_messages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['referenced_group_id']);
            $table->dropForeign(['referenced_group_message_id']);
            $table->dropColumn(['referenced_group_id', 'referenced_group_message_id']);
        });
    }
};
