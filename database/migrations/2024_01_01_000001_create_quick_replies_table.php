<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('quick_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('quick_reply_categories')->onDelete('set null');
            $table->string('title');
            $table->text('message');
            $table->string('shortcut')->nullable();
            $table->integer('order')->default(0);
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_global')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'category_id']);
            $table->index(['user_id', 'usage_count']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('quick_replies');
    }
};
