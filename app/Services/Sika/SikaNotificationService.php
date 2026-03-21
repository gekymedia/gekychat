<?php

namespace App\Services\Sika;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SikaNotificationService
{
    public function __construct(
        private FcmService $fcmService
    ) {}

    /**
     * Send notification when coins are transferred
     */
    public function notifyTransfer(
        int $fromUserId,
        int $toUserId,
        int $coins,
        ?string $note = null,
        string $transactionId = ''
    ): void {
        $this->sendCoinNotification(
            fromUserId: $fromUserId,
            toUserId: $toUserId,
            coins: $coins,
            type: 'transfer',
            note: $note,
            transactionId: $transactionId
        );
    }

    /**
     * Send notification when coins are gifted
     */
    public function notifyGift(
        int $fromUserId,
        int $toUserId,
        int $coins,
        ?string $note = null,
        ?int $postId = null,
        ?int $messageId = null,
        string $transactionId = ''
    ): void {
        $this->sendCoinNotification(
            fromUserId: $fromUserId,
            toUserId: $toUserId,
            coins: $coins,
            type: 'gift',
            note: $note,
            postId: $postId,
            relatedMessageId: $messageId,
            transactionId: $transactionId
        );
    }

    /**
     * Core method to send coin notification
     */
    private function sendCoinNotification(
        int $fromUserId,
        int $toUserId,
        int $coins,
        string $type,
        ?string $note = null,
        ?int $postId = null,
        ?int $relatedMessageId = null,
        string $transactionId = ''
    ): void {
        try {
            $sender = User::find($fromUserId);
            $recipient = User::find($toUserId);

            if (!$sender || !$recipient) {
                Log::warning('Sika notification: User not found', [
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $toUserId,
                ]);
                return;
            }

            // 1. Create or get conversation between sender and recipient
            $conversation = $this->getOrCreateConversation($fromUserId, $toUserId);

            // 2. Create system message in the conversation
            $message = $this->createSikaMessage(
                conversation: $conversation,
                sender: $sender,
                recipient: $recipient,
                coins: $coins,
                type: $type,
                note: $note,
                postId: $postId,
                relatedMessageId: $relatedMessageId,
                transactionId: $transactionId
            );

            // 3. Update conversation's last message
            $conversation->update([
                'last_message_id' => $message->id,
                'last_message_at' => now(),
            ]);

            // 4. Broadcast via Pusher for real-time update
            broadcast(new MessageSent($message))->toOthers();

            // 5. Send push notification
            $this->sendPushNotification(
                recipient: $recipient,
                sender: $sender,
                coins: $coins,
                type: $type,
                conversationId: $conversation->id,
                messageId: $message->id
            );

            Log::info('Sika coin notification sent', [
                'type' => $type,
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'coins' => $coins,
                'message_id' => $message->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send Sika notification', [
                'error' => $e->getMessage(),
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'coins' => $coins,
                'type' => $type,
            ]);
        }
    }

    /**
     * Get or create a conversation between two users (pivot + pair columns via ConversationService).
     */
    private function getOrCreateConversation(int $userOneId, int $userTwoId): Conversation
    {
        return Conversation::findOrCreateDirect($userOneId, $userTwoId, $userOneId);
    }

    /**
     * Create a special sika_transfer message
     */
    private function createSikaMessage(
        Conversation $conversation,
        User $sender,
        User $recipient,
        int $coins,
        string $type,
        ?string $note,
        ?int $postId,
        ?int $relatedMessageId,
        string $transactionId
    ): Message {
        $isGift = $type === 'gift';
        
        // Build the message body
        $body = $isGift
            ? "🎁 Sent you a gift of {$coins} Sika coins!"
            : "🪙 Sent you {$coins} Sika coins!";

        if ($note) {
            $body .= "\n\n\"{$note}\"";
        }

        // Build metadata for the special message type
        $metadata = [
            'sika_transfer' => true,
            'transfer_type' => $type, // 'transfer' or 'gift'
            'coins' => $coins,
            'transaction_id' => $transactionId,
            'note' => $note,
        ];

        if ($postId) {
            $metadata['post_id'] = $postId;
        }

        if ($relatedMessageId) {
            $metadata['related_message_id'] = $relatedMessageId;
        }

        $message = Message::create([
            'client_uuid' => Str::uuid()->toString(),
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => 'user',
            'body' => $body,
            'type' => 'sika_transfer', // Special message type
            'metadata' => $metadata,
        ]);

        return $message;
    }

    /**
     * Send push notification for coin transfer
     */
    private function sendPushNotification(
        User $recipient,
        User $sender,
        int $coins,
        string $type,
        int $conversationId,
        int $messageId
    ): void {
        $isGift = $type === 'gift';
        
        $title = $isGift ? '🎁 Gift Received!' : '🪙 Coins Received!';
        $body = $isGift
            ? "{$sender->name} sent you a gift of {$coins} Sika coins!"
            : "{$sender->name} sent you {$coins} Sika coins!";

        // Create deep links
        $appDeepLink = "gekychat://sika/wallet";
        $universalLink = "https://chat.gekychat.com/sika/wallet";

        $data = [
            'type' => 'sika_transfer',
            'transfer_type' => $type,
            'coins' => (string) $coins,
            'sender_id' => (string) $sender->id,
            'sender_name' => $sender->name,
            'conversation_id' => (string) $conversationId,
            'message_id' => (string) $messageId,
            'title' => $title,
            'body' => $body,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'deep_link' => $appDeepLink,
            'universal_link' => $universalLink,
        ];

        if ($sender->avatar_url) {
            $data['sender_avatar'] = $sender->avatar_url;
        }

        $this->fcmService->sendDataOnlyToUser(
            $recipient->id,
            $data,
            'gekychat_sika_' . $sender->id // Collapse key
        );
    }
}
