<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        // Sessions persist until explicit logout (all device types).
        DB::table('personal_access_tokens')
            ->whereNotNull('expires_at')
            ->update(['expires_at' => null]);
    }

    public function down(): void
    {
        // Irreversible: prior per-token expiry values are unknown.
    }
};
