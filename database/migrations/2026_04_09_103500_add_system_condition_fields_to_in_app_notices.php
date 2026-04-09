<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('in_app_notices', function (Blueprint $table) {
            if (!Schema::hasColumn('in_app_notices', 'is_system_notice')) {
                $table->boolean('is_system_notice')->default(false)->after('notice_key');
            }
            if (!Schema::hasColumn('in_app_notices', 'condition_type')) {
                $table->string('condition_type', 64)->nullable()->after('sort_order');
            }
            if (!Schema::hasColumn('in_app_notices', 'condition_value')) {
                $table->json('condition_value')->nullable()->after('condition_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('in_app_notices', function (Blueprint $table) {
            if (Schema::hasColumn('in_app_notices', 'condition_value')) {
                $table->dropColumn('condition_value');
            }
            if (Schema::hasColumn('in_app_notices', 'condition_type')) {
                $table->dropColumn('condition_type');
            }
            if (Schema::hasColumn('in_app_notices', 'is_system_notice')) {
                $table->dropColumn('is_system_notice');
            }
        });
    }
};

