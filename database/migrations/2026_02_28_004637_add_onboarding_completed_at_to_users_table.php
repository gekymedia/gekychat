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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable()->after('phone_verified_at');
        });
        
        // Mark existing users as having completed onboarding (they're not new)
        \DB::table('users')
            ->whereNotNull('phone_verified_at')
            ->whereNull('onboarding_completed_at')
            ->update(['onboarding_completed_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });
    }
};
