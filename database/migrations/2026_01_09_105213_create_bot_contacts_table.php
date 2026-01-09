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
        Schema::create('bot_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('bot_number', 20)->unique(); // e.g., 0000000000, 0000000001
            $table->string('name'); // Bot name (e.g., "GekyBot", "Support Bot")
            $table->string('code', 6); // 6-digit login code (stored as plain text for admin display)
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable(); // Optional description
            $table->timestamps();
            
            $table->index('bot_number');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_contacts');
    }
};
