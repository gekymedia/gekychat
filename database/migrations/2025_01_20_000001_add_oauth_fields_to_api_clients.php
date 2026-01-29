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
        // Check if the table exists before trying to alter it
        // This migration might run before the create_api_clients_table migration
        if (!Schema::hasTable('api_clients')) {
            \Log::info('Skipping add_oauth_fields_to_api_clients migration: api_clients table does not exist yet');
            return;
        }

        Schema::table('api_clients', function (Blueprint $table) {
            // Check if columns already exist before adding them
            if (!Schema::hasColumn('api_clients', 'client_id')) {
                $table->string('client_id')->unique()->nullable()->after('id');
            }
            if (!Schema::hasColumn('api_clients', 'client_secret')) {
                $table->string('client_secret')->nullable()->after('client_id');
            }
            if (!Schema::hasColumn('api_clients', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('status');
            }
            if (!Schema::hasColumn('api_clients', 'scopes')) {
                $table->json('scopes')->nullable()->after('is_active');
            }
        });

        // Generate client_id and client_secret for existing records that don't have them
        if (Schema::hasTable('api_clients')) {
            \DB::table('api_clients')
                ->whereNull('client_id')
                ->get()
                ->each(function ($client) {
                    \DB::table('api_clients')
                        ->where('id', $client->id)
                        ->update([
                            'client_id' => \Illuminate\Support\Str::random(32),
                            'client_secret' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(64)),
                            'is_active' => isset($client->status) && $client->status === 'approved',
                            'scopes' => json_encode(['messages.send']),
                        ]);
                });
        }
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
