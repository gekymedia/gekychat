<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * Used for 24h call link expiry: link closes 24h after the last person joined.
     */
    public function up(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->timestamp('last_joined_at')->nullable()->after('ended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->dropColumn('last_joined_at');
        });
    }
};
