<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageStatus;
use App\Services\OpenAiService;
use App\Services\BotService;
use App\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendBirthdayReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $today = Carbon::today();
        $month = $today->month;
        $day = $today->day;

        Log::info("Running birthday reminder job for {$today->format('Y-m-d')} (month: {$month}, day: {$day})");

        // Find all users who have contacts with birthdays today
        // We need to find contacts where contact_user_id points to a user with dob_month and dob_day matching today
        $contactsWithBirthdays = Contact::whereHas('contactUser', function ($query) use ($month, $day) {
            $query->where('dob_month', $month)
                  ->where('dob_day', $day);
        })
        ->where('is_deleted', false)
        ->with(['owner', 'contactUser'])
        ->get();

        if ($contactsWithBirthdays->isEmpty()) {
            Log::info('No birthdays found for today');
            return;
        }

        Log::info("Found {$contactsWithBirthdays->count()} contacts with birthdays today");

        $botUserId = User::where('phone', '0000000000')->value('id');
        if (!$botUserId) {
            Log::error('GekyBot user not found (phone: 0000000000)');
            return;
        }

        $openAiService = app(OpenAiService::class);
        $botService = app(BotService::class);

        $remindersSent = 0;

        foreach ($contactsWithBirthdays as $contact) {
            try {
                $owner = $contact->owner;
                $contactUser = $contact->contactUser;

                if (!$owner || !$contactUser) {
                    continue;
                }

                // Get or create conversation between owner and bot
                $conversation = Conversation::findOrCreateDirect($owner->id, $botUserId, $owner->id);

                // Check if we already sent a birthday reminder today (avoid duplicates)
                $todayStart = Carbon::today()->startOfDay();
                $todayEnd = Carbon::today()->endOfDay();
                
                $existingReminder = Message::where('conversation_id', $conversation->id)
                    ->where('sender_id', $botUserId)
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->where('body', 'like', '%' . $contactUser->name . '%')
                    ->where('body', 'like', '%birthday%')
                    ->first();

                if ($existingReminder) {
                    Log::info("Birthday reminder already sent today for contact {$contactUser->name} to user {$owner->id}");
                    continue;
                }

                // Generate birthday reminder message using OpenAI if available
                $contactName = $contact->display_name ?? $contactUser->name ?? 'your contact';
                $userName = $owner->name ?? 'there';

                $reminderMessage = $openAiService->generateBirthdayReminder($contactName, $userName);

                // Send the reminder message
                $botService->sendBotMessage($conversation->id, $reminderMessage);

                $remindersSent++;
                Log::info("Sent birthday reminder to user {$owner->id} for contact {$contactName}");

                // Small delay to avoid rate limiting
                usleep(200000); // 200ms delay between messages

            } catch (\Exception $e) {
                Log::error("Failed to send birthday reminder for contact {$contact->id}: " . $e->getMessage());
                continue;
            }
        }

        Log::info("Birthday reminder job completed. Sent {$remindersSent} reminders.");
    }
}
