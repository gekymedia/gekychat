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
        Schema::create('audio_license_snapshots', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('audio_library_id');
            $table->unsignedBigInteger('world_feed_post_id')->nullable();
            
            // License details at time of snapshot
            $table->string('license_type', 50);
            $table->text('license_url');
            $table->text('license_full_text')->nullable();
            $table->json('freesound_metadata'); // Complete API response
            
            // Validation
            $table->timestamp('validated_at');
            $table->string('validated_by', 50)->default('system'); // 'system' or admin user_id
            $table->text('validation_notes')->nullable();
            
            // Compliance
            $table->boolean('is_compliant')->default(true);
            $table->json('compliance_issues')->nullable(); // Array of any issues found
            
            $table->timestamp('created_at')->useCurrent();
            
            // Foreign keys
            $table->foreign('audio_library_id')->references('id')->on('audio_library')->onDelete('cascade');
            $table->foreign('world_feed_post_id')->references('id')->on('world_feed_posts')->onDelete('set null');
            
            // Indexes
            $table->index('audio_library_id');
            $table->index('world_feed_post_id');
            $table->index(['is_compliant', 'validated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audio_license_snapshots');
    }
};
