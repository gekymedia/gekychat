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
        Schema::table('attachments', function (Blueprint $table) {
            // Make attachable_id and attachable_type nullable
            // This allows attachments to be created before being linked to messages
            $table->unsignedBigInteger('attachable_id')->nullable()->change();
            $table->string('attachable_type')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            // Revert to non-nullable (this may fail if there are null values)
            $table->unsignedBigInteger('attachable_id')->nullable(false)->change();
            $table->string('attachable_type')->nullable(false)->change();
        });
    }
};
