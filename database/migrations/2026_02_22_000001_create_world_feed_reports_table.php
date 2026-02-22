<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('world_feed_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('world_feed_posts')->onDelete('cascade');
            $table->foreignId('reporter_id')->constrained('users')->onDelete('cascade');
            $table->string('reason', 100)->default('inappropriate');
            $table->timestamps();

            $table->unique(['post_id', 'reporter_id']);
            $table->index('post_id');
            $table->index('reporter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('world_feed_reports');
    }
};
