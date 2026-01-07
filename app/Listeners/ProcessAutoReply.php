<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Models\AutoReplyRule;
use App\Models\AutoReplyCooldown;
use App\Models\Message;
use App\Models\Conversation;
use App\Services\FeatureFlagService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AUTO-REPLY: Process auto-reply when message is sent
 * 
 * Safety Rules:
 * - Only applies to one-to-one private chats (not groups, channels, world feed, live chat, email-chat)
 * - Anti-loop protection: 24-hour cooldown per conversation per rule
 * - Auto replies never trigger other auto replies
 * - Only processes plain text messages (ignores media-only messages)
 */
class ProcessAutoReply
{
    const COOLDOWN_HOURS = 24;

    /**
     * Handle the event.
     */
    public function handle(MessageSent $event): void
    {
        $message = $event->message;

        // Check feature flag
        if (!FeatureFlagService::isEnabled('auto_reply')) {
            return;
        }

        // ✅ SAFETY: Only process one-to-one private chats
        $conversation = $message->conversation;
        if (!$this->isValidConversation($conversation)) {
            return;
        }

        // ✅ SAFETY: Ignore media-only messages (only process messages with text body)
        if (empty($message->body) || trim($message->body) === '') {
            return;
        }

        // ✅ SAFETY: Never auto-reply to auto-replies (system_generated messages)
        if ($this->isSystemGenerated($message)) {
            return;
        }

        // Get recipient (the user who should receive the auto-reply)
        $recipientId = $this->getRecipientId($conversation, $message->sender_id);
        if (!$recipientId) {
            return;
        }

        // Get active auto-reply rules for recipient
        $rules = AutoReplyRule::where('user_id', $recipientId)
            ->active()
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        // Check each rule
        foreach ($rules as $rule) {
            // Check if keyword matches (case-insensitive)
            if (!$rule->matches($message->body)) {
                continue;
            }

            // ✅ ANTI-LOOP PROTECTION: Check cooldown
            if (!AutoReplyCooldown::canAutoReply($conversation->id, $rule->id, self::COOLDOWN_HOURS)) {
                Log::debug("Auto-reply skipped: cooldown active for conversation {$conversation->id}, rule {$rule->id}");
                continue;
            }

            // Queue or send auto-reply
            $this->sendAutoReply($conversation, $rule, $message);
        }
    }

    /**
     * ✅ SAFETY: Validate conversation is one-to-one private chat
     * Must NOT be: groups, channels, world feed, live chat, email-chat
     */
    private function isValidConversation(Conversation $conversation): bool
    {
        // Must be a direct (non-group) conversation
        if ($conversation->is_group) {
            return false;
        }

        // Check if it's an email chat conversation
        // Email conversations have metadata->email_conversation flag
        if ($conversation->metadata && isset($conversation->metadata['email_conversation']) && $conversation->metadata['email_conversation']) {
            return false;
        }

        // Check if it's a channel (should not exist in conversations table, but safety check)
        // Channels are separate entities, but double-check
        if (isset($conversation->metadata['is_channel']) && $conversation->metadata['is_channel']) {
            return false;
        }

        return true;
    }

    /**
     * ✅ SAFETY: Check if message is system-generated (auto-reply)
     * Auto replies must never trigger other auto replies
     */
    private function isSystemGenerated(Message $message): bool
    {
        // Check metadata for system_generated flag
        if ($message->metadata && isset($message->metadata['system_generated']) && $message->metadata['system_generated']) {
            return true;
        }

        // Check sender_type
        if ($message->sender_type === 'platform') {
            return true;
        }

        return false;
    }

    /**
     * Get the recipient ID (the user who should receive the auto-reply)
     */
    private function getRecipientId(Conversation $conversation, int $senderId): ?int
    {
        // For direct conversations, get the other participant
        $members = $conversation->members()->where('users.id', '!=', $senderId)->get();
        
        if ($members->isEmpty()) {
            return null;
        }

        return $members->first()->id;
    }

    /**
     * Send auto-reply message
     */
    private function sendAutoReply(Conversation $conversation, AutoReplyRule $rule, Message $originalMessage): void
    {
        DB::transaction(function () use ($conversation, $rule, $originalMessage) {
            // Create auto-reply message
            $autoReplyMessage = Message::create([
                'client_uuid' => Str::uuid(),
                'conversation_id' => $conversation->id,
                'sender_id' => $rule->user_id, // The user who owns the rule sends the reply
                'body' => $rule->reply_text,
                'metadata' => [
                    'system_generated' => true, // ✅ Mark as system-generated
                    'auto_reply_rule_id' => $rule->id,
                    'triggered_by_message_id' => $originalMessage->id,
                ],
            ]);

            // Update cooldown
            AutoReplyCooldown::updateOrCreateCooldown($conversation->id, $rule->id);

            // Handle delay if specified
            if ($rule->delay_seconds && $rule->delay_seconds > 0) {
                // Queue delayed message
                \App\Jobs\SendDelayedAutoReply::dispatch($autoReplyMessage)->delay(now()->addSeconds($rule->delay_seconds));
            } else {
                // Send immediately
                $autoReplyMessage->load(['sender', 'attachments', 'replyTo', 'forwardedFrom', 'reactions.user']);
                
                // Mark as delivered for recipients
                $recipients = $conversation->members()->where('users.id', '!=', $rule->user_id)->get();
                foreach ($recipients as $recipient) {
                    $autoReplyMessage->markAsDeliveredFor($recipient->id);
                }

                // Broadcast message
                broadcast(new \App\Events\MessageSent($autoReplyMessage))->toOthers();
            }

            Log::info("Auto-reply sent", [
                'conversation_id' => $conversation->id,
                'rule_id' => $rule->id,
                'message_id' => $autoReplyMessage->id,
                'delay_seconds' => $rule->delay_seconds,
            ]);
        });
    }
}
