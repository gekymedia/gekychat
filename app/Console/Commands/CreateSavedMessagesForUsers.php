<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Console\Command;

class CreateSavedMessagesForUsers extends Command
{
    protected $signature = 'users:create-saved-messages 
                            {--user= : Create for a specific user ID or phone number}
                            {--all : Create for all users who don\'t have saved messages}';

    protected $description = 'Create saved messages conversation for users who don\'t have one';

    public function handle(): int
    {
        $specificUser = $this->option('user');
        $all = $this->option('all');

        if (!$specificUser && !$all) {
            $this->error('Please specify --user=<id|phone> or --all');
            return 1;
        }

        if ($specificUser) {
            return $this->createForUser($specificUser);
        }

        return $this->createForAllUsers();
    }

    protected function createForUser(string $identifier): int
    {
        // Try to find by ID first, then by phone
        $user = User::find($identifier) ?? User::where('phone', $identifier)->first();

        if (!$user) {
            $this->error("User not found: {$identifier}");
            return 1;
        }

        $this->info("Processing user: {$user->name} (ID: {$user->id}, Phone: {$user->phone})");

        // Check if saved messages already exists
        $existing = Conversation::savedMessages($user->id)->first();

        if ($existing) {
            $this->info("  ✓ Saved messages already exists (ID: {$existing->id}, Slug: {$existing->slug})");
            return 0;
        }

        // Create saved messages
        $savedMessages = Conversation::findOrCreateSavedMessages($user->id);
        $this->info("  ✓ Created saved messages (ID: {$savedMessages->id}, Slug: {$savedMessages->slug})");

        return 0;
    }

    protected function createForAllUsers(): int
    {
        $this->info('Creating saved messages for all users without one...');

        $users = User::all();
        $created = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            // Check if saved messages already exists
            $existing = Conversation::savedMessages($user->id)->first();

            if ($existing) {
                $skipped++;
            } else {
                try {
                    Conversation::findOrCreateSavedMessages($user->id);
                    $created++;
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->error("Failed to create for user {$user->id}: {$e->getMessage()}");
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Done! Created: {$created}, Skipped (already exists): {$skipped}");

        return 0;
    }
}
