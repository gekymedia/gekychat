<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_broadcasts', function (Blueprint $table) {
            if (! Schema::hasColumn('live_broadcasts', 'likes_count')) {
                $table->unsignedBigInteger('likes_count')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('live_broadcasts', function (Blueprint $table) {
            if (Schema::hasColumn('live_broadcasts', 'likes_count')) {
                $table->dropColumn('likes_count');
            }
        });
    }
};
