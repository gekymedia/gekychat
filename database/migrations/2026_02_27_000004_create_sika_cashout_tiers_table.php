<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sika_cashout_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('min_coins');
            $table->bigInteger('max_coins')->nullable();
            $table->decimal('ghs_per_million_coins', 12, 2);
            $table->decimal('fee_percent', 5, 2)->default(0);
            $table->decimal('fee_flat_ghs', 12, 2)->default(0);
            $table->integer('daily_limit')->nullable();
            $table->integer('weekly_limit')->nullable();
            $table->integer('monthly_limit')->nullable();
            $table->integer('hold_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['is_active', 'min_coins']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sika_cashout_tiers');
    }
};
