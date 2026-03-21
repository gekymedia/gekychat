<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\BotService;
use App\Models\User;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receive webhooks from BlackTask
 * Notifies users in GekyChat about task events
 */
class BlackTaskWebhookController extends Controller
{
    /**
     * Handle incoming webhook from BlackTask
     */
    public function handle(Request $request, BotService $botService)
    {
        // Verify webhook token
        $token = $request->bearerToken();
        $expectedToken = config('services.blacktask.webhook_token');

        if (!$expectedToken || $token !== $expectedToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'event' => 'required|string',
            'task' => 'required|array',
            'user' => 'required|array',
            'user.phone' => 'required|string',
        ]);

        try {
            $event = $request->input('event');
            $task = $request->input('task');
            $userPhone = $request->input('user.phone');

            // Find GekyChat user by phone
            $user = User::where('phone', $userPhone)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found in GekyChat'
                ]);
            }

            $botUserId = User::where('phone', '0000000000')->value('id');
            if (! $botUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bot user not configured',
                ], 500);
            }

            // Same code path as API DMs: pivot + denormalized columns stay in sync
            $conversation = Conversation::findOrCreateDirect($user->id, (int) $botUserId);

            // Format notification message based on event
            $message = $this->formatNotification($event, $task);

            // Send notification to user
            $botService->sendBotMessage($conversation->id, $message);

            return response()->json([
                'success' => true,
                'message' => 'Notification sent'
            ]);

        } catch (\Exception $e) {
            Log::error('BlackTask webhook error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error processing webhook'
            ], 500);
        }
    }

    /**
     * Format notification message based on event type
     */
    private function formatNotification(string $event, array $task): string
    {
        $title = $task['title'] ?? 'Task';
        $date = $task['task_date'] ?? 'today';
        $priority = $task['priority'] ?? 1;

        $priorityIcon = match($priority) {
            2 => '🔴',
            0 => '🟢',
            default => '🟡'
        };

        return match($event) {
            'task.created' => "✅ **Task Created**\n\n{$priorityIcon} {$title}\n📅 Due: {$date}",
            'task.completed' => "🎉 **Task Completed!**\n\n{$title}\n\nGreat job! Keep up the momentum! 💪",
            'task.deleted' => "🗑️ **Task Deleted**\n\n{$title}",
            'task.updated' => "📝 **Task Updated**\n\n{$priorityIcon} {$title}\n📅 Due: {$date}",
            'task.reminder' => "⏰ **Task Reminder**\n\n{$priorityIcon} {$title}\n📅 Due: {$date}\n\nDon't forget!",
            default => "📋 **Task Event:** {$event}\n\n{$title}"
        };
    }
}
