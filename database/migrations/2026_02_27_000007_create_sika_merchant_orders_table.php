<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sika_merchant_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('sika_merchants')->onDelete('cascade');
            $table->foreignId('buyer_user_id')->constrained('users')->onDelete('cascade');
            
            $table->string('order_reference')->unique();
            $table->bigInteger('coins_amount');
            $table->bigInteger('commission_coins')->default(0);
            $table->bigInteger('net_coins');
            
            $table->enum('status', [
                'pending',
                'paid',
                'completed',
                'refunded',
                'cancelled',
                'disputed',
            ])->default('pending');
            
            $table->string('idempotency_key')->unique();
            $table->uuid('ledger_group_id')->nullable();
            
            $table->text('description')->nullable();
            $table->json('items')->nullable();
            $table->json('meta')->nullable();
            
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['merchant_id', 'status']);
            $table->index(['buyer_user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sika_merchant_orders');
    }
};
