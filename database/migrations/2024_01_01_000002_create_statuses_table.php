<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['text', 'image', 'video']);
            $table->text('content');
            $table->string('media_path')->nullable();
            $table->string('background_color')->default('#000000');
            $table->string('text_color')->default('#FFFFFF');
            $table->integer('font_size')->default(24);
            $table->integer('duration')->default(86400); // seconds
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('statuses');
    }
};
