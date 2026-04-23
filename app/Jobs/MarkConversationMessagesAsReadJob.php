<?php

namespace App\Jobs;

use App\Events\MessageRead;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageStatus;
use Illuminate\Support\Facades\Cache;

/**
 * Marks DM messages as read and broadcasts; runs after the HTTP response (terminating)
 * so chat pages return quickly without blocking on read receipts.
 */
class MarkConversationMessagesAsReadJob
{
    public function __construct(
        public int $conversationId,
        public int $userId
    ) {}

    public function handle(): void
    {
        $conversation = Conversation::find($this->conversationId);
        if (! $conversation || ! $conversation->isParticipant($this->userId)) {
            return;
        }

        $unreadMessages = Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $this->userId)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->visibleTo($this->userId)
            ->whereDoesntHave('statuses', function ($query) {
                $query->where('user_id', $this->userId)
                    ->where('status', MessageStatus::STATUS_READ)
                    ->whereNull('deleted_at');
            })
            ->pluck('id');

        if ($unreadMessages->isNotEmpty()) {
            $unreadIds = $unreadMessages->all();
            $maxMessageId = max($unreadIds);

            $statuses = [];
            foreach ($unreadIds as $messageId) {
                $statuses[] = [
                    'message_id' => $messageId,
                    'user_id' => $this->userId,
                    'status' => MessageStatus::STATUS_READ,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            MessageStatus::insertOrIgnore($statuses);

            $conversation->members()->updateExistingPivot($this->userId, [
                'last_read_message_id' => $maxMessageId,
            ]);

            broadcast(new MessageRead($conversation->id, $this->userId, $unreadIds))->toOthers();
        } else {
            $latestMessageId = Message::where('conversation_id', $conversation->id)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->visibleTo($this->userId)
                ->max('id');
            if ($latestMessageId) {
                $conversation->members()->updateExistingPivot($this->userId, [
                    'last_read_message_id' => $latestMessageId,
                ]);
            }
        }

        Cache::forget("chat:sidebar:user:{$this->userId}");
    }
}
