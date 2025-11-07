<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a banned_until column to the users table. This timestamp allows
 * administrators to temporarily suspend a user from the platform.
 * When the current date is less than banned_until the user should be
 * considered banned (see User model for helper methods). The column
 * can safely be nullable and will be cast to a Carbon instance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'banned_until')) {
                $table->timestamp('banned_until')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'banned_until')) {
                $table->dropColumn('banned_until');
            }
        });
    }
};