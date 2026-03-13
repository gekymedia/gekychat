<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-side expense records for GekyChat (real money / bank reconciliation).
     * external_transaction_id links to Priority Bank transactions for 2-way sync.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // e.g. hosting, sms, ads, salaries, other
            $table->string('vendor')->nullable();
            $table->string('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('spent_at')->index();
            $table->string('reference')->nullable();
            $table->string('external_transaction_id', 64)->nullable();
            $table->timestamps();

            $table->index('external_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
