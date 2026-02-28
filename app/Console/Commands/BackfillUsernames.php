<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class BackfillUsernames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:backfill-usernames {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate usernames for existing users who do not have one';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $usersWithoutUsername = User::whereNull('username')
            ->orWhere('username', '')
            ->count();
        
        $this->info("Found {$usersWithoutUsername} users without usernames.");
        
        if ($usersWithoutUsername === 0) {
            $this->info('All users already have usernames. Nothing to do.');
            return 0;
        }
        
        if ($dryRun) {
            $this->warn('Dry run mode - no changes will be made.');
            
            User::whereNull('username')
                ->orWhere('username', '')
                ->chunk(100, function ($users) {
                    foreach ($users as $user) {
                        $newUsername = User::generateUniqueUsername();
                        $this->line("Would assign username '{$newUsername}' to user #{$user->id} ({$user->name})");
                    }
                });
            
            return 0;
        }
        
        if (!$this->confirm("Do you want to generate usernames for {$usersWithoutUsername} users?")) {
            $this->info('Operation cancelled.');
            return 0;
        }
        
        $bar = $this->output->createProgressBar($usersWithoutUsername);
        $bar->start();
        
        $updated = 0;
        
        User::whereNull('username')
            ->orWhere('username', '')
            ->chunk(100, function ($users) use (&$updated, $bar) {
                foreach ($users as $user) {
                    $user->username = User::generateUniqueUsername();
                    $user->save();
                    $updated++;
                    $bar->advance();
                }
            });
        
        $bar->finish();
        $this->newLine();
        
        $this->info("Successfully generated usernames for {$updated} users.");
        
        return 0;
    }
}
