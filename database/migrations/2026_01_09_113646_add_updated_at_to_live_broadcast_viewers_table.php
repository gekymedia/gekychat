<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('live_broadcast_viewers', function (Blueprint $table) {
            if (!Schema::hasColumn('live_broadcast_viewers', 'created_at')) {
                $table->timestamp('created_at')->nullable()->after('left_at');
            }
            if (!Schema::hasColumn('live_broadcast_viewers', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_broadcast_viewers', function (Blueprint $table) {
            if (Schema::hasColumn('live_broadcast_viewers', 'created_at')) {
                $table->dropColumn('created_at');
            }
            if (Schema::hasColumn('live_broadcast_viewers', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};
