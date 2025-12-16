<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('code', 6);
            $table->timestamp('expires_at');
            $table->boolean('verified')->default(false);
            $table->timestamps();
            
            $table->index(['phone', 'code', 'expires_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('otp_codes');
    }
};

