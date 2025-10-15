<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Phone (create if missing)
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable()->after('password');
            }

            // OTP fields
            if (!Schema::hasColumn('users', 'otp_code')) {
                $table->string('otp_code', 6)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'otp_expires_at')) {
                $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
            }

            // Phone verification timestamp
            if (!Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            }

            // Email 2FA fields (used in your controller)
            if (!Schema::hasColumn('users', 'two_factor_code')) {
                $table->string('two_factor_code', 6)->nullable()->after('phone_verified_at');
            }
            if (!Schema::hasColumn('users', 'two_factor_expires_at')) {
                $table->timestamp('two_factor_expires_at')->nullable()->after('two_factor_code');
            }

            // Admin flag (optional, used in your model)
            if (!Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false)->after('two_factor_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop only what we added
            $drops = [];
            foreach ([
                'otp_code', 'otp_expires_at',
                'phone_verified_at',
                'two_factor_code', 'two_factor_expires_at',
                'is_admin',
            ] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $drops[] = $col;
                }
            }
            if (!empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
