<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // First, drop the existing quick_replies table (data will be lost!)
        Schema::dropIfExists('quick_replies');
        
        // Drop the categories table
        Schema::dropIfExists('quick_reply_categories');

        // Create the simplified quick_replies table
        Schema::create('quick_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable(); // Made nullable for flexibility
            $table->text('message');
            $table->integer('order')->default(0);
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // Indexes for better performance
            $table->index(['user_id', 'order']);
            $table->index(['user_id', 'usage_count']);
            $table->index('last_used_at');
        });
    }

    public function down()
    {
        // Drop the simplified table
        Schema::dropIfExists('quick_replies');

        // Recreate the original structure
        Schema::create('quick_reply_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

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
};