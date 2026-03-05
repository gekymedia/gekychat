<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure messages table has sender_type (and related platform columns).
     * Safe to run when the original migration was recorded but schema wasn't applied.
     */
    public function up(): void
    {
        if (!Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'sender_type')) {
                $table->string('sender_type')->nullable()->after('sender_id')->default('user');
            }

            if (!Schema::hasColumn('messages', 'platform_client_id')) {
                if (Schema::hasTable('api_clients')) {
                    $table->foreignId('platform_client_id')->nullable()->after('sender_type')
                        ->constrained('api_clients')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('platform_client_id')->nullable()->after('sender_type');
                }
            }

            if (!Schema::hasColumn('messages', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally no-op: do not drop columns that may have been added by the original migration
    }
};
