<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sika_cashout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('wallet_id')->constrained('sika_wallets')->onDelete('cascade');
            $table->foreignId('tier_id')->constrained('sika_cashout_tiers')->onDelete('restrict');
            
            $table->bigInteger('coins_requested');
            $table->decimal('ghs_to_credit', 12, 2);
            $table->decimal('fee_applied', 12, 2)->default(0);
            $table->decimal('net_ghs', 12, 2);
            
            $table->enum('status', [
                'PENDING',
                'APPROVED',
                'REJECTED',
                'PROCESSING',
                'PAID',
                'FAILED',
                'REVERSED',
            ])->default('PENDING');
            
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->string('pbg_credit_reference')->nullable();
            $table->string('idempotency_key')->unique();
            
            $table->text('rejection_reason')->nullable();
            $table->json('meta')->nullable();
            
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('available_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sika_cashout_requests');
    }
};
