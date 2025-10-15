<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   // database/migrations/[timestamp]_create_message_attachments_table.php
public function up()
{
    Schema::create('message_attachments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('message_id')->constrained()->onDelete('cascade');
        $table->string('file_path');
        $table->string('file_type');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
    }
};
