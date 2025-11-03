<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('session_id')->unique();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type')->nullable(); // desktop, mobile, tablet
            $table->string('browser')->nullable();
            $table->string('platform')->nullable(); // windows, macos, linux, android, ios
            $table->string('location')->nullable(); // country, city
            $table->boolean('is_current')->default(false);
            $table->timestamp('last_activity');
            $table->timestamps();

            $table->index(['user_id', 'last_activity']);
            $table->index('session_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_sessions');
    }
};