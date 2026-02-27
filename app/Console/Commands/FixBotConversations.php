<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Console\Command;

class FixBotConversations extends Command
{
    protected $signature = 'conversations:fix-bot 
                            {--dry-run : Show what would be fixed without making changes}';

    protected $description = 'Fix bot conversations that have only 1 member (should have 2)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        // Find the bot user
        $botPhone = config('app.bot_phone', '0000000000');
        $botUser = User::where('phone', $botPhone)->first();
        
        if (!$botUser) {
            $this->error("Bot user not found with phone: {$botPhone}");
            return 1;
        }
        
        $this->info("Bot user found: ID={$botUser->id}, Name={$botUser->name}");
        
        // Find conversations with the bot that have only 1 member
        $brokenConversations = Conversation::query()
            ->where('is_group', false)
            ->whereHas('members', fn($q) => $q->where('users.id', $botUser->id))
            ->has('members', '=', 1)
            ->get();
        
        if ($brokenConversations->isEmpty()) {
            $this->info('No broken bot conversations found. All bot conversations have 2 members.');
            return 0;
        }
        
        $this->warn("Found {$brokenConversations->count()} bot conversations with only 1 member:");
        
        foreach ($brokenConversations as $conv) {
            $member = $conv->members->first();
            $this->line("  - Conversation ID: {$conv->id}, Slug: {$conv->slug}, Member: {$member->name} (ID: {$member->id})");
            
            if (!$dryRun) {
                // The conversation has only the bot - we need to find who created it
                // and add them as a member
                $createdBy = $conv->created_by;
                
                if ($createdBy && $createdBy !== $botUser->id) {
                    // Add the creator as a member
                    $conv->members()->syncWithoutDetaching([$createdBy => ['role' => 'member']]);
                    $this->info("    ✓ Added user {$createdBy} as member");
                } else {
                    $this->warn("    ⚠ Could not determine which user to add (created_by: {$createdBy})");
                }
            }
        }
        
        if ($dryRun) {
            $this->newLine();
            $this->info('This was a dry run. Run without --dry-run to fix these conversations.');
        } else {
            $this->newLine();
            $this->info('Done fixing bot conversations.');
        }
        
        return 0;
    }
}
