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
        Schema::table('users', function (Blueprint $table) {
            // Google OAuth fields
            $table->text('google_access_token')->nullable()->after('settings');
            $table->text('google_refresh_token')->nullable()->after('google_access_token');
            $table->string('google_email')->nullable()->after('google_refresh_token');
            $table->timestamp('last_google_sync_at')->nullable()->after('google_email');
            $table->boolean('google_sync_enabled')->default(false)->after('last_google_sync_at');
            
            // Index for better performance
            $table->index('google_email', 'users_google_email_index');
            $table->index('last_google_sync_at', 'users_last_google_sync_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('users_google_email_index');
            $table->dropIndex('users_last_google_sync_at_index');
            
            // Drop columns
            $table->dropColumn([
                'google_access_token',
                'google_refresh_token',
                'google_email',
                'last_google_sync_at',
                'google_sync_enabled',
            ]);
        });
    }
};