<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds:
     * - auto_add_to_contacts: Whether this bot is automatically added to new users' contacts
     * - bot_type: Type of bot for routing (general, admissions, tasks)
     * - avatar_path: Custom avatar for the bot
     */
    public function up(): void
    {
        Schema::table('bot_contacts', function (Blueprint $table) {
            $table->boolean('auto_add_to_contacts')->default(false)->after('is_active');
            $table->string('bot_type', 50)->default('general')->after('auto_add_to_contacts');
            $table->string('avatar_path')->nullable()->after('description');
        });
        
        // Update existing GekyChat AI bot to auto-add
        DB::table('bot_contacts')
            ->where('bot_number', '0000000000')
            ->update([
                'auto_add_to_contacts' => true,
                'bot_type' => 'general'
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_contacts', function (Blueprint $table) {
            $table->dropColumn(['auto_add_to_contacts', 'bot_type', 'avatar_path']);
        });
    }
};
