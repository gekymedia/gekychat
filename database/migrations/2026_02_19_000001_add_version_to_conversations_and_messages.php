<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds version column for optimistic concurrency control (arch-061).
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('updated_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('version');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
