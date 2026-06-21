<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phone is the primary account identity (OTP login). Legacy email-only
     * accounts are backfilled with a non-dialable placeholder before NOT NULL.
     */
    public function up(): void
    {
        $legacyUserIds = DB::table('users')
            ->where(function ($query) {
                $query->whereNull('phone')->orWhere('phone', '');
            })
            ->pluck('id');

        foreach ($legacyUserIds as $userId) {
            DB::table('users')
                ->where('id', $userId)
                ->update(['phone' => '_legacy_'.$userId]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
        });
    }
};
