<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('broadcast_lists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
        });

        Schema::create('broadcast_list_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('broadcast_list_id');
            $table->unsignedBigInteger('recipient_id'); // user_id of the recipient
            $table->timestamps();

            $table->foreign('broadcast_list_id')->references('id')->on('broadcast_lists')->cascadeOnDelete();
            $table->foreign('recipient_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['broadcast_list_id', 'recipient_id']);
            $table->index('broadcast_list_id');
            $table->index('recipient_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('broadcast_list_recipients');
        Schema::dropIfExists('broadcast_lists');
    }
};

