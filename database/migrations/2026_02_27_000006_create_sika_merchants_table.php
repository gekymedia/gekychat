<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sika_merchants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->onDelete('cascade');
            $table->string('business_name');
            $table->string('business_type')->nullable();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            
            $table->enum('status', [
                'pending',
                'active',
                'suspended',
                'rejected',
            ])->default('pending');
            
            $table->string('merchant_code')->unique()->nullable();
            $table->decimal('commission_percent', 5, 2)->default(0);
            
            $table->json('settings')->nullable();
            $table->json('meta')->nullable();
            
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status']);
            $table->index(['owner_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sika_merchants');
    }
};
