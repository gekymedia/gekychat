<?php

namespace App\Console\Commands;

use App\Models\ScheduledMessage;
use App\Models\Message;
use App\Models\GroupMessage;
use Illuminate\Console\Command;

class ProcessScheduledMessages extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'process:scheduled-messages';

    /**
     * The console command description.
     */
    protected $description = 'Send scheduled messages that are due';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if scheduled_messages table exists
        if (!\Schema::hasTable('scheduled_messages')) {
            $this->info('Scheduled messages table does not exist yet. Skipping processing.');
            return 0;
        }
        
        $dueMessages = ScheduledMessage::where('status', 'pending')
            ->where('scheduled_for', '<=', now())
            ->get();
        
        if ($dueMessages->isEmpty()) {
            $this->info('No scheduled messages due for sending');
            return 0;
        }
        
        $sent = 0;
        $failed = 0;
        
        foreach ($dueMessages as $scheduled) {
            try {
                // Check if it's a group message or 1-on-1 message
                if ($scheduled->group_id) {
                    // Group message
                    $message = GroupMessage::create([
                        'group_id' => $scheduled->group_id,
                        'sender_id' => $scheduled->user_id,
                        'body' => $scheduled->body,
                        'reply_to_id' => $scheduled->reply_to_id,
                    ]);
                } else {
                    // 1-on-1 message
                    $message = Message::create([
                        'conversation_id' => $scheduled->conversation_id,
                        'sender_id' => $scheduled->user_id,
                        'body' => $scheduled->body,
                        'reply_to_id' => $scheduled->reply_to_id,
                    ]);
                }
                
                // Mark as sent
                $scheduled->update([
                    'status' => 'sent',
                    'sent_message_id' => $message->id,
                ]);
                
                $sent++;
                $this->info("✅ Sent scheduled message #{$scheduled->id}");
                
            } catch (\Exception $e) {
                $scheduled->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                
                $failed++;
                $this->error("❌ Failed to send scheduled message #{$scheduled->id}: " . $e->getMessage());
            }
        }
        
        $this->info("📊 Processed {$dueMessages->count()} scheduled messages: {$sent} sent, {$failed} failed");
        
        return 0;
    }
}
