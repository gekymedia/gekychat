<?php

namespace Database\Seeders;

use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformApiClientsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates API clients for external platforms (CUG and schoolsgh)
     * that will integrate with GekyChat Platform API.
     */
    public function run(): void
    {
        // Get or create a system admin user to own these API clients
        $adminUser = User::where('is_admin', true)->first();
        
        if (!$adminUser) {
            // Create a system admin user if none exists
            $adminUser = User::create([
                'name' => 'System Admin',
                'email' => 'admin@gekychat.com',
                'phone' => '0000000001',
                'normalized_phone' => '+2330000000001',
                'password' => Hash::make(Str::random(32)),
                'is_admin' => true,
                'phone_verified_at' => now(),
            ]);
        }

        // CUG Platform API Client
        $cugClientId = 'cug_platform_' . substr(md5('cug_platform_client'), 0, 16);
        $cugClient = ApiClient::where('client_id', 'like', 'cug_platform_%')->first();
        
        if (!$cugClient) {
            $cugClient = ApiClient::create([
                'user_id' => $adminUser->id,
                'client_id' => $cugClientId,
                'client_secret' => Hash::make(Str::random(64)),
                'callback_url' => env('CUG_WEBHOOK_URL', 'https://cug.example.com/webhooks/gekychat'),
                'features' => ['messages.send', 'users.manage', 'conversations.manage'],
                'status' => 'approved',
                'is_active' => true,
                'scopes' => ['messages.send', 'users.read', 'users.create', 'conversations.read', 'conversations.create'],
            ]);
        } else {
            // Update existing client but preserve client_id and client_secret if they exist
            $cugClient->update([
                'user_id' => $adminUser->id,
                'callback_url' => env('CUG_WEBHOOK_URL', $cugClient->callback_url),
                'is_active' => true,
                'status' => 'approved',
                'scopes' => ['messages.send', 'users.read', 'users.create', 'conversations.read', 'conversations.create'],
            ]);
            
            // Only set client_id if it doesn't exist
            if (empty($cugClient->client_id)) {
                $cugClient->update(['client_id' => $cugClientId]);
            }
            
            // Only set client_secret if it doesn't exist
            if (empty($cugClient->client_secret)) {
                $cugClient->update(['client_secret' => Hash::make(Str::random(64))]);
            }
        }

        // Schoolsgh Platform API Client
        $schoolsghClientId = 'schoolsgh_platform_' . substr(md5('schoolsgh_platform_client'), 0, 16);
        $schoolsghClient = ApiClient::where('client_id', 'like', 'schoolsgh_platform_%')->first();
        
        if (!$schoolsghClient) {
            $schoolsghClient = ApiClient::create([
                'user_id' => $adminUser->id,
                'client_id' => $schoolsghClientId,
                'client_secret' => Hash::make(Str::random(64)),
                'callback_url' => env('SCHOOLSGH_WEBHOOK_URL', 'https://schoolsgh.example.com/webhooks/gekychat'),
                'features' => ['messages.send', 'users.manage', 'conversations.manage'],
                'status' => 'approved',
                'is_active' => true,
                'scopes' => ['messages.send', 'users.read', 'users.create', 'conversations.read', 'conversations.create'],
            ]);
        } else {
            // Update existing client but preserve client_id and client_secret if they exist
            $schoolsghClient->update([
                'user_id' => $adminUser->id,
                'callback_url' => env('SCHOOLSGH_WEBHOOK_URL', $schoolsghClient->callback_url),
                'is_active' => true,
                'status' => 'approved',
                'scopes' => ['messages.send', 'users.read', 'users.create', 'conversations.read', 'conversations.create'],
            ]);
            
            // Only set client_id if it doesn't exist
            if (empty($schoolsghClient->client_id)) {
                $schoolsghClient->update(['client_id' => $schoolsghClientId]);
            }
            
            // Only set client_secret if it doesn't exist
            if (empty($schoolsghClient->client_secret)) {
                $schoolsghClient->update(['client_secret' => Hash::make(Str::random(64))]);
            }
        }

        // Display credentials (only in console)
        if ($this->command) {
            $this->command->info('✅ Platform API Clients Created:');
            $this->command->newLine();
            
            $this->command->info('📱 CUG Platform API Client:');
            $this->command->line('   Client ID: ' . $cugClient->client_id);
            $this->command->line('   Status: ' . ($cugClient->is_active ? '✅ Active' : '❌ Inactive'));
            $this->command->newLine();
            
            $this->command->info('🏫 Schoolsgh Platform API Client:');
            $this->command->line('   Client ID: ' . $schoolsghClient->client_id);
            $this->command->line('   Status: ' . ($schoolsghClient->is_active ? '✅ Active' : '❌ Inactive'));
            $this->command->newLine();
            
            $this->command->warn('⚠️  IMPORTANT: Generate plain text secrets by running:');
            $this->command->line('   php artisan db:seed --class=GenerateApiClientSecretsSeeder');
            $this->command->newLine();
            $this->command->line('   Then add the credentials to your .env files:');
            $this->command->line('   For CUG:');
            $this->command->line('   GEKYCHAT_CLIENT_ID=' . $cugClient->client_id);
            $this->command->line('   GEKYCHAT_CLIENT_SECRET=<from GenerateApiClientSecretsSeeder>');
            $this->command->newLine();
            $this->command->line('   For Schoolsgh:');
            $this->command->line('   GEKYCHAT_CLIENT_ID=' . $schoolsghClient->client_id);
            $this->command->line('   GEKYCHAT_CLIENT_SECRET=<from GenerateApiClientSecretsSeeder>');
        }
    }
}
