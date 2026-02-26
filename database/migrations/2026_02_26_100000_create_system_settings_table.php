<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, integer, float, boolean, json
            $table->string('group')->default('general'); // general, engagement_boost, etc.
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default engagement boost settings
        DB::table('system_settings')->insert([
            [
                'key' => 'engagement_boost_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'engagement_boost',
                'description' => 'Enable/disable engagement boost multipliers for World Feed posts',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'engagement_boost_views_multiplier',
                'value' => '5',
                'type' => 'float',
                'group' => 'engagement_boost',
                'description' => 'Multiplier for views count (e.g., 5 means 10 views shows as 50)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'engagement_boost_likes_multiplier',
                'value' => '3',
                'type' => 'float',
                'group' => 'engagement_boost',
                'description' => 'Multiplier for likes count',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'engagement_boost_comments_multiplier',
                'value' => '2',
                'type' => 'float',
                'group' => 'engagement_boost',
                'description' => 'Multiplier for comments count',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'engagement_boost_shares_multiplier',
                'value' => '2',
                'type' => 'float',
                'group' => 'engagement_boost',
                'description' => 'Multiplier for shares count',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
