<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * PHASE 2: Create user accounts table for multi-account support
     * 
     * Tracks multiple user accounts on a single device (mobile/desktop only).
     * Each account has separate tokens, storage, and notifications.
     */
    public function up(): void
    {
        // Add device_id to personal_access_tokens to track which device the token belongs to
        if (Schema::hasTable('personal_access_tokens')) {
            if (!Schema::hasColumn('personal_access_tokens', 'device_id')) {
                Schema::table('personal_access_tokens', function (Blueprint $table) {
                    $table->string('device_id')->nullable()->after('tokenable_id'); // Device identifier (mobile/desktop)
                    $table->string('device_type')->nullable()->after('device_id'); // 'mobile' or 'desktop'
                    $table->string('account_label')->nullable()->after('device_type'); // User-defined label for account
                    $table->index(['device_id', 'device_type']);
                    $table->index(['tokenable_id', 'device_id']);
                });
            }
        }

        // Create device_accounts table to track accounts per device
        Schema::create('device_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('device_id'); // Unique device identifier
            $table->string('device_type'); // 'mobile' or 'desktop'
            $table->unsignedBigInteger('user_id'); // Account user
            $table->string('account_label')->nullable(); // User-defined label (e.g., "Work", "Personal")
            $table->boolean('is_active')->default(false); // Currently active account
            $table->timestamp('last_used_at')->nullable(); // Last time this account was used
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['device_id', 'device_type', 'user_id']); // One account per device-user combo
            $table->index(['device_id', 'device_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_accounts');

        if (Schema::hasTable('personal_access_tokens')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->dropIndex(['device_id', 'device_type']);
                $table->dropIndex(['tokenable_id', 'device_id']);
                $table->dropColumn(['device_id', 'device_type', 'account_label']);
            });
        }
    }
};
