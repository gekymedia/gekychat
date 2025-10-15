<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Ensure email is nullable (keeps any unique index intact)
        if (Schema::hasColumn('users', 'email')) {
            // raw SQL avoids doctrine/dbal
            DB::statement("ALTER TABLE `users` MODIFY `email` VARCHAR(255) NULL");
        }

        Schema::table('users', function (Blueprint $table) {
            // 2) Ensure phone & security fields exist
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'otp_code')) {
                $table->unsignedInteger('otp_code')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'otp_expires_at')) {
                $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
            }
            if (!Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('otp_expires_at');
            }
            if (!Schema::hasColumn('users', 'two_factor_code')) {
                $table->string('two_factor_code', 6)->nullable()->after('phone_verified_at');
            }
            if (!Schema::hasColumn('users', 'two_factor_expires_at')) {
                $table->timestamp('two_factor_expires_at')->nullable()->after('two_factor_code');
            }
        });

        // 3) Add a UNIQUE index on phone (if not already there)
        // Check if an index named 'users_phone_unique' exists; if not, create it.
        $hasUnique = collect(DB::select("SHOW INDEX FROM `users` WHERE Key_name = 'users_phone_unique'"))->isNotEmpty();

        if (!$hasUnique && Schema::hasColumn('users', 'phone')) {
            // Before creating the unique index, you must resolve duplicates (if any),
            // otherwise MySQL will error out. See notes below.
            Schema::table('users', function (Blueprint $table) {
                $table->unique('phone', 'users_phone_unique');
            });
        }
    }

    public function down(): void
    {
        // Drop unique index on phone if present
        $hasUnique = collect(DB::select("SHOW INDEX FROM `users` WHERE Key_name = 'users_phone_unique'"))->isNotEmpty();
        if ($hasUnique) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_phone_unique');
            });
        }

        // (Optional) revert email back to NOT NULL if you want:
        // DB::statement("ALTER TABLE `users` MODIFY `email` VARCHAR(255) NOT NULL");
    }
};
