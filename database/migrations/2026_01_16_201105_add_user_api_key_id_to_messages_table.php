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
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'user_api_key_id')) {
                // Add column first without foreign key
                $table->unsignedBigInteger('user_api_key_id')->nullable();
                $table->index('user_api_key_id');
                
                // Add foreign key constraint only if table exists
                if (Schema::hasTable('user_api_keys')) {
                    $table->foreign('user_api_key_id')
                        ->references('id')
                        ->on('user_api_keys')
                        ->onDelete('set null');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'user_api_key_id')) {
                $table->dropForeign(['user_api_key_id']);
                $table->dropColumn('user_api_key_id');
            }
        });
    }
};
