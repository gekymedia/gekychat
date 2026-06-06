<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('device_tokens')) {
            return;
        }

        Schema::table('device_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('device_tokens', 'voip_token')) {
                $table->string('voip_token', 255)->nullable()->after('token');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('device_tokens')) {
            return;
        }

        Schema::table('device_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('device_tokens', 'voip_token')) {
                $table->dropColumn('voip_token');
            }
        });
    }
};
