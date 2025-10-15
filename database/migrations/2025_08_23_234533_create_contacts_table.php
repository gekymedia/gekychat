<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            // Owner of the address book entry
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // If this phone belongs to a registered user, link them
            $table->foreignId('contact_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('display_name')->nullable();

            // Raw and normalized phone (for matching)
            $table->string('phone', 64);                // what the device sent
            $table->string('normalized_phone', 64);     // digits-only (optionally with +)

            $table->string('source', 32)->default('device'); // device/manual/import
            $table->boolean('is_favorite')->default(false);

            // Optional visuals/metadata (you can drop these if not needed)
            $table->string('avatar_path')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            // A user should not have duplicate entries per normalized number
            $table->unique(['user_id', 'normalized_phone']);

            $table->index('normalized_phone');
            $table->index('contact_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
