<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status comments are removed in favour of DM replies (referenced_status_id on messages).
     */
    public function up(): void
    {
        Schema::dropIfExists('status_comments');
    }

    public function down(): void
    {
        Schema::create('status_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_id')->constrained('statuses')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('comment');
            $table->timestamps();

            $table->index(['status_id', 'created_at']);
            $table->index('user_id');
        });
    }
};
