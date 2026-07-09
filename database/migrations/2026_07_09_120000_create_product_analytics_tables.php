<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_analytics_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 32)->default('unknown'); // desktop, mobile, web, android, ios, windows, macos, linux
            $table->string('app_version', 64)->nullable();
            $table->string('device_type', 64)->nullable();
            $table->string('os_version', 64)->nullable();
            $table->string('locale', 16)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('screen_views_count')->default(0);
            $table->unsignedInteger('events_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index(['platform', 'started_at']);
            $table->index(['is_active', 'last_heartbeat_at']);
        });

        Schema::create('product_analytics_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_uuid')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_name', 64); // session_start, screen_view, action, session_end
            $table->string('feature_key', 64)->nullable(); // chats, status, ai, calls, ...
            $table->string('action_key', 64)->nullable(); // message_sent, call_started, ...
            $table->json('properties')->nullable();
            $table->string('platform', 32)->default('unknown');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['event_name', 'occurred_at']);
            $table->index(['feature_key', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['session_uuid', 'occurred_at']);
        });

        Schema::create('product_analytics_daily_rollups', function (Blueprint $table) {
            $table->id();
            $table->date('rollup_date');
            $table->string('metric_key', 64);
            $table->string('dimension', 64)->default('all');
            $table->decimal('value', 20, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['rollup_date', 'metric_key', 'dimension'], 'pa_rollups_unique');
            $table->index(['metric_key', 'rollup_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_analytics_daily_rollups');
        Schema::dropIfExists('product_analytics_events');
        Schema::dropIfExists('product_analytics_sessions');
    }
};
