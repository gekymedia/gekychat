<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('world_feed_posts', function (Blueprint $table) {
            $table->string('share_code', 12)->unique()->nullable()->after('id');
            $table->index('share_code');
        });
    }

    public function down(): void
    {
        Schema::table('world_feed_posts', function (Blueprint $table) {
            $table->dropIndex(['share_code']);
            $table->dropColumn('share_code');
        });
    }
};
