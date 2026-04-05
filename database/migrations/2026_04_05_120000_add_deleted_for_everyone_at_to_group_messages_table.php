<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('group_messages', 'deleted_for_everyone_at')) {
                $table->timestamp('deleted_for_everyone_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_messages', function (Blueprint $table) {
            if (Schema::hasColumn('group_messages', 'deleted_for_everyone_at')) {
                try {
                    $table->dropIndex(['deleted_for_everyone_at']);
                } catch (\Throwable $e) {
                }
                $table->dropColumn('deleted_for_everyone_at');
            }
        });
    }
};
