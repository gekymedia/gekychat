<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   // database/migrations/[timestamp]_add_phone_and_otp_to_users_table.php
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('phone')->unique()->nullable();
        $table->string('otp_code')->nullable();
        $table->timestamp('otp_expires_at')->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
