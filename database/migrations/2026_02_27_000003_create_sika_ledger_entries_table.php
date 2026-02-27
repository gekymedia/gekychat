<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sika_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('sika_wallets')->onDelete('cascade');
            
            $table->enum('type', [
                'PURCHASE_CREDIT',
                'TRANSFER_OUT',
                'TRANSFER_IN',
                'GIFT_OUT',
                'GIFT_IN',
                'SPEND',
                'MERCHANT_PAY',
                'MERCHANT_RECEIVE',
                'CASHOUT_DEBIT',
                'REFUND',
                'ADMIN_ADJUST',
            ]);
            
            $table->enum('direction', ['CREDIT', 'DEBIT']);
            $table->bigInteger('coins');
            $table->enum('status', ['PENDING', 'POSTED', 'REVERSED'])->default('PENDING');
            
            $table->uuid('group_id')->nullable();
            
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            
            $table->string('idempotency_key')->unique();
            
            $table->json('meta')->nullable();
            
            $table->bigInteger('balance_after')->nullable();
            
            $table->timestamps();
            
            $table->index(['wallet_id', 'status', 'created_at']);
            $table->index(['group_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sika_ledger_entries');
    }
};
