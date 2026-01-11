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
        Schema::table('live_broadcasts', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('room_name');
            $table->index('slug');
        });
        
        // Generate slugs for existing broadcasts
        \App\Models\LiveBroadcast::whereNull('slug')->chunk(100, function ($broadcasts) {
            foreach ($broadcasts as $broadcast) {
                $broadcast->slug = $broadcast->generateSlug();
                $broadcast->save();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_broadcasts', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropColumn('slug');
        });
    }
};
