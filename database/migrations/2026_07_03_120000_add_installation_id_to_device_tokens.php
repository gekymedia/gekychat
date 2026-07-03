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
            if (! Schema::hasColumn('device_tokens', 'installation_id')) {
                $table->uuid('installation_id')->nullable()->after('device_id');
                $table->index(['user_id', 'installation_id'], 'device_tokens_user_installation_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('device_tokens')) {
            return;
        }

        Schema::table('device_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('device_tokens', 'installation_id')) {
                $table->dropIndex('device_tokens_user_installation_idx');
                $table->dropColumn('installation_id');
            }
        });
    }
};
