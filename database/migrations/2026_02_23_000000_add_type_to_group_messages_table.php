<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('group_messages') && !Schema::hasColumn('group_messages', 'type')) {
            Schema::table('group_messages', function (Blueprint $table) {
                $table->string('type')->nullable()->after('body'); // text, image, video, audio, document, voice, poll, contact, location, etc.
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('group_messages', 'type')) {
            Schema::table('group_messages', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
