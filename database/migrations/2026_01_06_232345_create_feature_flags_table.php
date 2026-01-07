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
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // e.g., 'stealth_status', 'delete_for_everyone'
            $table->boolean('enabled')->default(false);
            $table->json('conditions')->nullable(); // e.g., {"beta_users": true, "platform": ["mobile", "desktop"]}
            $table->string('platform')->default('all'); // 'web', 'mobile', 'desktop', 'all'
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index('key');
            $table->index('enabled');
            $table->index('platform');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
