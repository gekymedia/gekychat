<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_broadcast_gifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('live_broadcasts')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            $table->string('gift_type', 50); // rose, heart, star, diamond, rocket, etc.
            $table->unsignedInteger('coins');
            $table->string('message', 200)->nullable();
            $table->timestamps();
            
            $table->index(['broadcast_id', 'created_at']);
            $table->index('sender_id');
            $table->index('receiver_id');
        });
        
        // Add total gifts received to live_broadcasts
        Schema::table('live_broadcasts', function (Blueprint $table) {
            $table->unsignedInteger('gifts_count')->default(0)->after('viewers_count');
            $table->unsignedBigInteger('gifts_total')->default(0)->after('gifts_count');
        });
    }

    public function down(): void
    {
        Schema::table('live_broadcasts', function (Blueprint $table) {
            $table->dropColumn(['gifts_count', 'gifts_total']);
        });
        
        Schema::dropIfExists('live_broadcast_gifts');
    }
};
