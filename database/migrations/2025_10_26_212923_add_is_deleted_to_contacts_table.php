<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('is_deleted')->default(false)->after('is_favorite');
            // $table->string('source')->default('manual')->after('is_deleted'); // 'manual' or 'google_sync'
            $table->unsignedBigInteger('google_contact_id')->nullable()->after('source');
            
            // Add indexes for better performance
            $table->index(['user_id', 'is_deleted']);
            $table->index(['user_id', 'source']);
        });
    }

    public function down()
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['is_deleted', 'source', 'google_contact_id']);
            $table->dropIndex(['user_id', 'is_deleted']);
            $table->dropIndex(['user_id', 'source']);
        });
    }
    
};