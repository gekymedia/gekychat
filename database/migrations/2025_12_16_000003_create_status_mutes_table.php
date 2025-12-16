<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('status_mutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('muted_user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['user_id', 'muted_user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('status_mutes');
    }
};

