<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Add profile columns if missing
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'avatar_path')) {
                $table->string('avatar_path')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'about')) {
                $table->string('about', 160)->nullable()->after('avatar_path');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                // Create phone with unique index when adding it the first time
                $table->string('phone', 20)->nullable(false)->unique()->after('password');
            }
        });

        // 2) If phone column exists but isn't unique yet, create the unique index
        if (Schema::hasColumn('users', 'phone')) {
            $idx = DB::select("SHOW INDEX FROM `users` WHERE Key_name = 'users_phone_unique'");
            if (empty($idx)) {
                // Use a dedicated Schema::table call so Laravel names the index correctly
                Schema::table('users', function (Blueprint $table) {
                    $table->unique('phone', 'users_phone_unique');
                });
            }
        }

        // 3) Make email nullable without requiring doctrine/dbal (raw SQL)
        try {
            DB::statement("ALTER TABLE `users` MODIFY `email` VARCHAR(255) NULL");
        } catch (\Throwable $e) {
            // If your column length differs, try a generic ALTER:
            try {
                DB::statement("ALTER TABLE `users` MODIFY `email` TEXT NULL");
            } catch (\Throwable $ignored) {
                // Leave as-is if your platform doesn't allow this form
            }
        }
    }

    public function down(): void
    {
        // Rollbacks only drop the new profile columns.
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'about')) {
                $table->dropColumn('about');
            }
            if (Schema::hasColumn('users', 'avatar_path')) {
                $table->dropColumn('avatar_path');
            }
            // We do NOT drop phone or its unique index on down() to avoid breaking logins.
        });
    }
};
