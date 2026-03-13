<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-side income records for GekyChat (real money / bank reconciliation).
     * external_transaction_id links to Priority Bank transactions for 2-way sync.
     */
    public function up(): void
    {
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->string('category')->nullable(); // e.g. subscriptions, tips, other
            $table->decimal('amount', 12, 2);
            $table->date('date')->index();
            $table->string('description')->nullable();
            $table->string('reference')->nullable();
            $table->string('external_transaction_id', 64)->nullable();
            $table->timestamps();

            $table->index('external_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
