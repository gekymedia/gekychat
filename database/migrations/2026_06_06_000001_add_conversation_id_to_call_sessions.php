<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('call_sessions', 'conversation_id')) {
                $table->foreignId('conversation_id')
                    ->nullable()
                    ->after('callee_id')
                    ->constrained('conversations')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('call_sessions', 'conversation_id')) {
                $table->dropConstrainedForeignId('conversation_id');
            }
        });
    }
};
