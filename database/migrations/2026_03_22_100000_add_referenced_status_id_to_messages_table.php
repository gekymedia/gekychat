<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('referenced_status_id')->nullable()->after('reply_to');
            $table->foreign('referenced_status_id')
                ->references('id')
                ->on('statuses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['referenced_status_id']);
            $table->dropColumn('referenced_status_id');
        });
    }
};
