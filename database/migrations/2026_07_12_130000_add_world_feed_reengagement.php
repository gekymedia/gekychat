<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_preferences')
            && !Schema::hasColumn('notification_preferences', 'push_world_reengagement')) {
            Schema::table('notification_preferences', function (Blueprint $table) {
                $table->boolean('push_world_reengagement')
                    ->default(true)
                    ->after('push_mentions');
            });
        }

        if (!Schema::hasTable('world_feed_reengagement_sends')) {
            Schema::create('world_feed_reengagement_sends', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('post_id')->constrained('world_feed_posts')->cascadeOnDelete();
                $table->timestamp('sent_at');
                $table->string('send_date', 10); // Y-m-d for daily dedupe
                $table->timestamps();

                $table->unique(['user_id', 'send_date']);
                $table->index(['sent_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('world_feed_reengagement_sends');

        if (Schema::hasTable('notification_preferences')
            && Schema::hasColumn('notification_preferences', 'push_world_reengagement')) {
            Schema::table('notification_preferences', function (Blueprint $table) {
                $table->dropColumn('push_world_reengagement');
            });
        }
    }
};
