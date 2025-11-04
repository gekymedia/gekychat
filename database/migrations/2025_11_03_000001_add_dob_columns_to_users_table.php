<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add separate month and day fields for optional birthdays. We avoid using a full
            // date column to sidestep year privacy concerns. Values range from 1–12 and 1–31.
            if (!Schema::hasColumn('users', 'dob_month')) {
                $table->unsignedTinyInteger('dob_month')->nullable()->after('last_seen_at');
            }
            if (!Schema::hasColumn('users', 'dob_day')) {
                $table->unsignedTinyInteger('dob_day')->nullable()->after('dob_month');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'dob_day')) {
                $table->dropColumn('dob_day');
            }
            if (Schema::hasColumn('users', 'dob_month')) {
                $table->dropColumn('dob_month');
            }
        });
    }
};