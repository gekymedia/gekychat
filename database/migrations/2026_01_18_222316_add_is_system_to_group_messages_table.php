<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('group_messages', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('sender_id');
            $table->string('system_action')->nullable()->after('is_system'); // e.g., 'joined', 'left', 'promoted', etc.
        });
    }

    public function down(): void
    {
        Schema::table('group_messages', function (Blueprint $table) {
            $table->dropColumn(['is_system', 'system_action']);
        });
    }
};
