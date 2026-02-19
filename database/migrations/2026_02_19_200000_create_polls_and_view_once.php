<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Polls ──────────────────────────────────────────────────────────────
        Schema::create('message_polls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->unsignedBigInteger('group_message_id')->nullable();
            $table->string('question');
            $table->boolean('allow_multiple')->default(false);
            $table->boolean('is_anonymous')->default(false);
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();

            $table->index('message_id');
            $table->index('group_message_id');
        });

        Schema::create('message_poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('message_polls')->cascadeOnDelete();
            $table->string('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('message_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('message_polls')->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('message_poll_options')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['poll_id', 'option_id', 'user_id']);
            $table->index(['poll_id', 'user_id']);
        });

        // ── View Once (one-time media) ─────────────────────────────────────────
        // Add is_view_once flag to messages and group_messages
        if (Schema::hasTable('messages') && !Schema::hasColumn('messages', 'is_view_once')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->boolean('is_view_once')->default(false)->after('type');
                $table->timestamp('viewed_at')->nullable()->after('is_view_once');
            });
        }

        if (Schema::hasTable('group_messages') && !Schema::hasColumn('group_messages', 'is_view_once')) {
            Schema::table('group_messages', function (Blueprint $table) {
                $table->boolean('is_view_once')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_poll_votes');
        Schema::dropIfExists('message_poll_options');
        Schema::dropIfExists('message_polls');

        if (Schema::hasColumn('messages', 'is_view_once')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn(['is_view_once', 'viewed_at']);
            });
        }

        if (Schema::hasColumn('group_messages', 'is_view_once')) {
            Schema::table('group_messages', function (Blueprint $table) {
                $table->dropColumn('is_view_once');
            });
        }
    }
};
