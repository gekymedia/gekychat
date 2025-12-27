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
        // Add call_id to conversations table
        Schema::table('conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('conversations', 'call_id')) {
                $table->string('call_id', 32)->unique()->nullable()->after('slug');
                $table->index('call_id');
            }
        });

        // Add call_id to groups table
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'call_id')) {
                $table->string('call_id', 32)->unique()->nullable()->after('slug');
                $table->index('call_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'call_id')) {
                $table->dropIndex(['call_id']);
                $table->dropColumn('call_id');
            }
        });

        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'call_id')) {
                $table->dropIndex(['call_id']);
                $table->dropColumn('call_id');
            }
        });
    }
};

