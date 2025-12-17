<?php

namespace Database\Seeders;

use App\Models\ApiClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GenerateApiClientSecretsSeeder extends Seeder
{
    /**
     * Generate and display plain text secrets for API clients.
     * 
     * This seeder generates new secrets for API clients and displays them
     * so you can copy them to your .env files. The secrets are hashed
     * before being stored in the database.
     */
    public function run(): void
    {
        $clients = ApiClient::where(function($query) {
            $query->where('client_id', 'like', 'cug_platform_%')
                  ->orWhere('client_id', 'like', 'schoolsgh_platform_%');
        })->get();

        if ($clients->isEmpty()) {
            $this->command->warn('No platform API clients found. Run PlatformApiClientsSeeder first.');
            return;
        }

        $this->command->info('🔑 Generating new secrets for Platform API Clients...');
        $this->command->newLine();

        foreach ($clients as $client) {
            // Generate a new plain text secret
            $plainSecret = Str::random(64);
            
            // Hash it for storage
            $hashedSecret = Hash::make($plainSecret);
            
            // Update the client
            $client->update(['client_secret' => $hashedSecret]);
            
            // Display credentials
            $platform = str_contains($client->client_id, 'cug') ? 'CUG' : 'Schoolsgh';
            
            $this->command->info("📱 {$platform} Platform API Client:");
            $this->command->line("   Client ID: {$client->client_id}");
            $this->command->line("   Client Secret: {$plainSecret}");
            $this->command->newLine();
            
            $this->command->warn("   Add to .env file:");
            $this->command->line("   GEKYCHAT_CLIENT_ID={$client->client_id}");
            $this->command->line("   GEKYCHAT_CLIENT_SECRET={$plainSecret}");
            $this->command->newLine();
        }

        $this->command->info('✅ Secrets generated and saved to database.');
        $this->command->warn('⚠️  Copy the secrets above to your .env files now!');
    }
}
