<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('statuses', function (Blueprint $table) {
            // Rename 'content' to 'text' for consistency with mobile app
            $table->renameColumn('content', 'text');
        });

        Schema::table('statuses', function (Blueprint $table) {
            // Make text nullable (for image/video statuses without caption)
            $table->text('text')->nullable()->change();
            
            // Add thumbnail_url for video statuses
            $table->string('thumbnail_url', 500)->nullable()->after('media_path');
            
            // Rename media_path to media_url
            $table->renameColumn('media_path', 'media_url');
        });

        Schema::table('statuses', function (Blueprint $table) {
            // Add expires_at timestamp (24 hours from creation)
            $table->timestamp('expires_at')->nullable()->after('duration');
            
            // Add font_family for text statuses
            $table->string('font_family', 50)->nullable()->after('font_size');
            
            // Add view_count for quick access
            $table->integer('view_count')->default(0)->after('duration');
            
            // Add soft deletes
            $table->softDeletes();
            
            // Update indexes
            $table->index('expires_at');
            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down()
    {
        Schema::table('statuses', function (Blueprint $table) {
            $table->dropColumn(['thumbnail_url', 'expires_at', 'font_family', 'view_count', 'deleted_at']);
            $table->dropIndex(['expires_at']);
            $table->dropIndex(['user_id', 'expires_at']);
            $table->renameColumn('media_url', 'media_path');
            $table->renameColumn('text', 'content');
        });
    }
};

