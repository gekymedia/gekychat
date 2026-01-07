<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * PHASE 2: Create phase modes and testing mode tables
     */
    public function up(): void
    {
        // Phase modes (server-wide configuration)
        Schema::create('phase_modes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 'basic', 'essential', 'comfort'
            $table->boolean('is_active')->default(false);
            $table->json('limits')->nullable(); // Custom limits override
            $table->timestamps();

            $table->unique('name');
        });

        // Testing mode (user-scoped overrides)
        Schema::create('testing_modes', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->json('user_ids')->nullable(); // Allowlisted user IDs
            $table->integer('max_lives')->default(1);
            $table->integer('max_test_rooms')->default(3);
            $table->integer('max_test_users')->default(10);
            $table->json('features')->nullable(); // Available features: ['group_video', 'live_broadcast', 'screen_sharing']
            $table->timestamps();
        });

        // Insert default phase modes
        DB::table('phase_modes')->insert([
            ['name' => 'basic', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'essential', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'comfort', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('testing_modes');
        Schema::dropIfExists('phase_modes');
    }
};
