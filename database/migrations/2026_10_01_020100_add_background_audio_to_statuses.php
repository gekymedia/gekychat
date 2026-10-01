<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            if (!Schema::hasColumn('statuses', 'audio_library_id')) {
                $table->unsignedBigInteger('audio_library_id')->nullable()->after('font_family');
                $table->unsignedTinyInteger('audio_volume')->default(100)->after('audio_library_id');
                $table->boolean('audio_loop')->default(true)->after('audio_volume');

                $table->foreign('audio_library_id')
                    ->references('id')
                    ->on('audio_library')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            if (Schema::hasColumn('statuses', 'audio_library_id')) {
                $table->dropForeign(['audio_library_id']);
                $table->dropColumn(['audio_library_id', 'audio_volume', 'audio_loop']);
            }
        });
    }
};
