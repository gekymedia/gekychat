<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * MEDIA COMPRESSION: Add compression tracking fields to attachments table
     * Tracks compression status and stores paths to compressed versions
     */
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('compression_status')->default('pending')->after('size'); // 'pending', 'processing', 'completed', 'failed'
            $table->string('compressed_file_path')->nullable()->after('compression_status'); // Path to compressed version
            $table->string('thumbnail_path')->nullable()->after('compressed_file_path'); // Path to thumbnail
            $table->integer('original_size')->nullable()->after('thumbnail_path'); // Original file size before compression
            $table->integer('compressed_size')->nullable()->after('original_size'); // Size after compression
            $table->string('compression_level')->default('medium')->after('compressed_size'); // 'low', 'medium', 'high'
            $table->text('compression_error')->nullable()->after('compression_level'); // Error message if compression failed
            
            $table->index('compression_status'); // For finding pending/processing items
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropIndex(['compression_status']);
            $table->dropColumn([
                'compression_status',
                'compressed_file_path',
                'thumbnail_path',
                'original_size',
                'compressed_size',
                'compression_level',
                'compression_error',
            ]);
        });
    }
};
