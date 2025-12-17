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
        Schema::table('api_clients', function (Blueprint $table) {
            // OAuth client credentials
            $table->string('client_id')->unique()->nullable()->after('id');
            $table->string('client_secret')->nullable()->after('client_id');
            
            // Status and scopes
            $table->boolean('is_active')->default(true)->after('status');
            $table->json('scopes')->nullable()->after('is_active');
        });

        // Generate client_id and client_secret for existing records
        \DB::table('api_clients')->get()->each(function ($client) {
            \DB::table('api_clients')
                ->where('id', $client->id)
                ->update([
                    'client_id' => \Illuminate\Support\Str::random(32),
                    'client_secret' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(64)),
                    'is_active' => $client->status === 'approved',
                    'scopes' => json_encode(['messages.send']),
                ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            $table->dropColumn(['client_id', 'client_secret', 'is_active', 'scopes']);
        });
    }
};
