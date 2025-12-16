<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Check if device_tokens table exists, if not create it
        if (!Schema::hasTable('device_tokens')) {
            Schema::create('device_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->text('token');
                $table->enum('device_type', ['android', 'ios', 'web'])->default('android');
                $table->string('device_id', 255)->nullable();
                $table->timestamps();
                
                $table->unique(['user_id', 'device_id']);
            });
        } else {
            // Update existing table if it exists
            Schema::table('device_tokens', function (Blueprint $table) {
                if (!Schema::hasColumn('device_tokens', 'device_type')) {
                    $table->enum('device_type', ['android', 'ios', 'web'])->default('android')->after('token');
                }
                if (!Schema::hasColumn('device_tokens', 'device_id')) {
                    $table->string('device_id', 255)->nullable()->after('device_type');
                }
            });
        }
    }

    public function down()
    {
        // Don't drop if it existed before, just remove added columns
        if (Schema::hasTable('device_tokens')) {
            Schema::table('device_tokens', function (Blueprint $table) {
                if (Schema::hasColumn('device_tokens', 'device_type')) {
                    $table->dropColumn('device_type');
                }
                if (Schema::hasColumn('device_tokens', 'device_id')) {
                    $table->dropColumn('device_id');
                }
            });
        }
    }
};

