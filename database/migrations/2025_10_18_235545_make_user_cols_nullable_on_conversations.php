<?php

// database/migrations/2025_10_18_000001_make_user_cols_nullable_on_conversations.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'user_one_id')) {
                $table->unsignedBigInteger('user_one_id')->nullable()->change();
            }
            if (Schema::hasColumn('conversations', 'user_two_id')) {
                $table->unsignedBigInteger('user_two_id')->nullable()->change();
            }
        });
    }

    public function down(): void {
        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'user_one_id')) {
                $table->unsignedBigInteger('user_one_id')->nullable(false)->change();
            }
            if (Schema::hasColumn('conversations', 'user_two_id')) {
                $table->unsignedBigInteger('user_two_id')->nullable(false)->change();
            }
        });
    }
};
