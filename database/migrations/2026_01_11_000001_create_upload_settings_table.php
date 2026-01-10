<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Creates global upload settings table for admin-configurable video upload limits
     */
    public function up(): void
    {
        Schema::create('upload_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // 'world_feed_max_duration', 'chat_video_max_size', etc.
            $table->text('value'); // Store as JSON or string depending on type
            $table->string('type')->default('integer'); // 'integer', 'float', 'string'
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('key');
        });

        // Insert default settings
        DB::table('upload_settings')->insert([
            [
                'key' => 'world_feed_max_duration',
                'value' => '180', // 3 minutes in seconds
                'type' => 'integer',
                'description' => 'Maximum video duration for World Feed posts (in seconds). Default: 180 seconds (3 minutes).',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'chat_video_max_size',
                'value' => '10485760', // 10 MB in bytes
                'type' => 'integer',
                'description' => 'Maximum file size for chat videos (in bytes). Default: 10485760 bytes (10 MB).',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'status_max_duration',
                'value' => '180', // 3 minutes in seconds (same as World Feed)
                'type' => 'integer',
                'description' => 'Maximum video duration for Status videos (in seconds). Default: 180 seconds (3 minutes).',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upload_settings');
    }
};
