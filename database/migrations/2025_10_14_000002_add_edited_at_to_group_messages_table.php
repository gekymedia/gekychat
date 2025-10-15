<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add an edited_at column to the group_messages table.
 *
 * Group messages can be edited, but the original schema did not provide
 * a timestamp for when an edit occurred. The GroupMessage model and
 * controllers expect an `edited_at` field to exist so that the UI can
 * display when a message was last updated. This migration adds the
 * nullable edited_at timestamp if it does not already exist. It is
 * idempotent so running it multiple times will not break anything.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('group_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('group_messages', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('read_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_messages', function (Blueprint $table) {
            if (Schema::hasColumn('group_messages', 'edited_at')) {
                $table->dropColumn('edited_at');
            }
        });
    }
};