<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('status_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('viewed_at');
            
            $table->unique(['status_id', 'user_id']);
            $table->index(['status_id', 'viewed_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('status_views');
    }
};
