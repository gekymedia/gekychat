<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 32); // bug, crash, other
            $table->text('description');
            $table->string('source', 32)->default('settings'); // shake, settings
            $table->string('app_version', 64)->nullable();
            $table->string('platform', 32)->nullable();
            $table->string('device_model', 128)->nullable();
            $table->string('os_version', 64)->nullable();
            $table->string('screen_name', 255)->nullable();
            $table->json('diagnostics')->nullable();
            $table->string('screenshot_path')->nullable();
            $table->string('status', 32)->default('pending'); // pending, reviewed, resolved
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_reports');
    }
};
