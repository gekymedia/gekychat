<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->unsignedBigInteger('owner_id'); // creator/admin
            $table->string('description', 200)->nullable();
            $table->string('avatar_path')->nullable();
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['admin', 'member'])->default('member');
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('group_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('sender_id');
            $table->text('body')->nullable();
            $table->unsignedBigInteger('reply_to_id')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reply_to_id')->references('id')->on('group_messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_messages');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
    }
};
