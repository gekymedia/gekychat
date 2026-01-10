<?php

namespace App\Console\Commands;

use App\Services\BlackTaskService;
use Illuminate\Console\Command;

class TestBlackTaskIntegration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blacktask:test {phone? : Phone number to test with}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test BlackTask integration and API connectivity';

    /**
     * Execute the console command.
     */
    public function handle(BlackTaskService $blackTaskService)
    {
        $this->info('🧪 Testing BlackTask Integration...');
        $this->newLine();

        // Check configuration
        $this->info('1️⃣ Checking Configuration...');
        if (!$blackTaskService->isConfigured()) {
            $this->error('❌ BlackTask is not configured!');
            $this->warn('Please add BLACKTASK_URL and BLACKTASK_API_TOKEN to your .env file');
            return Command::FAILURE;
        }
        $this->info('✅ Configuration found');
        $this->newLine();

        // Test connection
        $this->info('2️⃣ Testing API Connection...');
        $connectionResult = $blackTaskService->testConnection();
        
        if ($connectionResult['success']) {
            $this->info('✅ Connection successful!');
        } else {
            $this->error('❌ Connection failed: ' . $connectionResult['message']);
            return Command::FAILURE;
        }
        $this->newLine();

        // Test with phone number if provided
        $phone = $this->argument('phone');
        if ($phone) {
            $this->info("3️⃣ Testing with phone number: {$phone}");
            
            // Get user
            $this->info('   → Looking up user...');
            $user = $blackTaskService->getUserByPhone($phone);
            
            if ($user) {
                $this->info("   ✅ User found: {$user['name']}");
                $this->newLine();
                
                // Get tasks
                $this->info('   → Fetching tasks...');
                $tasksResult = $blackTaskService->getTasks($phone);
                
                if ($tasksResult['success']) {
                    $taskCount = count($tasksResult['tasks']);
                    $this->info("   ✅ Found {$taskCount} tasks");
                    
                    if ($taskCount > 0) {
                        $this->newLine();
                        $this->info('   📋 Sample Tasks:');
                        foreach (array_slice($tasksResult['tasks'], 0, 3) as $task) {
                            $checkbox = $task['extendedProps']['is_done'] ? '✅' : '⬜';
                            $this->line("      {$checkbox} {$task['title']}");
                        }
                    }
                } else {
                    $this->warn('   ⚠️ Failed to fetch tasks: ' . $tasksResult['message']);
                }
                
                $this->newLine();
                
                // Get statistics
                $this->info('   → Fetching statistics...');
                $statsResult = $blackTaskService->getStatistics($phone);
                
                if ($statsResult['success']) {
                    $stats = $statsResult['statistics'];
                    $this->info('   ✅ Statistics retrieved');
                    $this->newLine();
                    $this->info('   📊 Task Statistics:');
                    $this->line("      Total: {$stats['total']}");
                    $this->line("      Completed: {$stats['completed']}");
                    $this->line("      Pending: {$stats['pending']}");
                    $this->line("      Overdue: {$stats['overdue']}");
                } else {
                    $this->warn('   ⚠️ Failed to fetch statistics: ' . $statsResult['message']);
                }
            } else {
                $this->warn("   ⚠️ User not found with phone: {$phone}");
                $this->warn('   Make sure the user has registered on BlackTask with this phone number');
            }
        } else {
            $this->info('3️⃣ Skipping user tests (no phone number provided)');
            $this->info('   Run with phone number: php artisan blacktask:test +233123456789');
        }

        $this->newLine();
        $this->info('✅ Integration test complete!');
        
        return Command::SUCCESS;
    }
}
