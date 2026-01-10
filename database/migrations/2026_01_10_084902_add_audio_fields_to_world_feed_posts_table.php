<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('world_feed_posts', function (Blueprint $table) {
            $table->boolean('has_audio')->default(false)->after('is_public');
            $table->text('audio_attribution')->nullable()->after('has_audio');
            
            $table->index('has_audio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('world_feed_posts', function (Blueprint $table) {
            $table->dropIndex(['has_audio']);
            $table->dropColumn(['has_audio', 'audio_attribution']);
        });
    }
};
