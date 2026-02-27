<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sika_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->bigInteger('balance_cached')->default(0);
            $table->enum('status', ['active', 'suspended', 'frozen'])->default('active');
            $table->timestamps();
            
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sika_wallets');
    }
};
