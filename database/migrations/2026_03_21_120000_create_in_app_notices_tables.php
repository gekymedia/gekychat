<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('in_app_notices', function (Blueprint $table) {
            $table->id();
            $table->string('notice_key')->unique();
            $table->string('title')->nullable();
            $table->text('body');
            $table->string('style', 32)->default('info'); // info | warning | promo
            $table->string('action_label')->nullable();
            $table->string('action_url', 2048)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('in_app_notice_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('notice_key');
            $table->timestamps();
            $table->unique(['user_id', 'notice_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_app_notice_dismissals');
        Schema::dropIfExists('in_app_notices');
    }
};
