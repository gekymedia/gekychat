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
        Schema::create('audio_library', function (Blueprint $table) {
            $table->id();
            
            // Freesound identifiers
            $table->unsignedInteger('freesound_id')->unique();
            $table->string('freesound_username', 100)->nullable();
            
            // Audio metadata
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('duration', 8, 2); // seconds
            $table->unsignedInteger('file_size')->nullable(); // bytes
            
            // Audio URLs
            $table->text('preview_url');
            $table->text('download_url')->nullable();
            $table->string('local_path', 500)->nullable();
            
            // License information (CRITICAL)
            $table->string('license_type', 50);
            $table->text('license_url');
            $table->json('license_snapshot'); // Full license at time of cache
            $table->boolean('attribution_required')->default(false);
            $table->text('attribution_text')->nullable();
            
            // Categorization
            $table->json('tags')->nullable();
            $table->string('category', 100)->nullable();
            
            // Usage tracking
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            
            // Caching metadata
            $table->timestamp('cached_at')->nullable();
            $table->timestamp('cache_expires_at')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->enum('validation_status', ['pending', 'approved', 'rejected'])->default('pending');
            
            $table->timestamps();
            
            // Indexes
            $table->index('freesound_id');
            $table->index('license_type');
            $table->index(['usage_count'], 'idx_usage_count');
            $table->index(['validation_status', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audio_library');
    }
};
