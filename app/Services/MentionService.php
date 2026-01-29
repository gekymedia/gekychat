<?php

namespace App\Services;

use App\Models\Message;
use App\Models\GroupMessage;
use App\Models\MessageMention;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MentionService
{
    /**
     * Parse message body and extract mentions
     * Supports: @username, @UserName, @user_name
     * 
     * @param string $body Message body
     * @return array Array of mentions with position and user info
     */
    public function parseMentions(string $body): array
    {
        $mentions = [];
        
        // Pattern matches: @username, @UserName, @user_name (letters, numbers, underscores)
        // Must start with @ and contain at least 3 characters
        preg_match_all('/@([a-zA-Z0-9_]{3,30})/', $body, $matches, PREG_OFFSET_CAPTURE);
        
        if (empty($matches[1])) {
            return $mentions;
        }
        
        foreach ($matches[1] as $index => $match) {
            $username = $match[0];
            $position = $match[1] - 1; // -1 because we need position of @ symbol
            
            $mentions[] = [
                'username' => $username,
                'position_start' => $position,
                'position_end' => $position + strlen($username) + 1, // +1 for @ symbol
                'full_match' => '@' . $username,
            ];
        }
        
        return $mentions;
    }

    /**
     * Validate and resolve mentions to actual users
     * 
     * @param array $mentions Parsed mentions from parseMentions()
     * @param int|null $groupId Group ID to validate membership (null for 1-on-1)
     * @return Collection Collection of User models that were mentioned
     */
    public function resolveMentions(array $mentions, ?int $groupId = null): Collection
    {
        if (empty($mentions)) {
            return collect();
        }
        
        $usernames = collect($mentions)->pluck('username')->unique()->toArray();
        
        $query = User::whereIn('username', $usernames);
        
        // If it's a group message, only allow mentioning group members
        if ($groupId) {
            $query->whereHas('groups', function ($q) use ($groupId) {
                $q->where('groups.id', $groupId);
            });
        }
        
        return $query->get();
    }

    /**
     * Create mention records for a message
     * 
     * @param Message|GroupMessage $message
     * @param int $senderId
     * @param int|null $groupId
     * @return int Number of mentions created
     */
    public function createMentions($message, int $senderId, ?int $groupId = null): int
    {
        $parsedMentions = $this->parseMentions($message->body);
        
        if (empty($parsedMentions)) {
            return 0;
        }
        
        $users = $this->resolveMentions($parsedMentions, $groupId);
        
        if ($users->isEmpty()) {
            return 0;
        }
        
        // Map usernames to user IDs
        $usernameToId = $users->pluck('id', 'username')->toArray();
        
        $mentionsCreated = 0;
        
        foreach ($parsedMentions as $mention) {
            $userId = $usernameToId[$mention['username']] ?? null;
            
            if (!$userId) {
                continue; // Username not found or not a group member
            }
            
            // Don't create mention if user mentions themselves
            if ($userId === $senderId) {
                continue;
            }
            
            MessageMention::create([
                'mentionable_type' => get_class($message),
                'mentionable_id' => $message->id,
                'mentioned_user_id' => $userId,
                'mentioned_by_user_id' => $senderId,
                'position_start' => $mention['position_start'],
                'position_end' => $mention['position_end'],
            ]);
            
            $mentionsCreated++;
        }
        
        // Update mention count on message
        $message->update(['mention_count' => $mentionsCreated]);
        
        return $mentionsCreated;
    }

    /**
     * Get unread mentions for a user
     * 
     * @param int $userId
     * @param int $limit
     * @return Collection
     */
    public function getUnreadMentions(int $userId, int $limit = 50): Collection
    {
        return MessageMention::forUser($userId)
            ->unread()
            ->withRelations()
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Mark mentions as read for a specific message
     * 
     * @param Message|GroupMessage $message
     * @param int $userId
     * @return int Number of mentions marked as read
     */
    public function markMentionsAsRead($message, int $userId): int
    {
        return MessageMention::where('mentionable_type', get_class($message))
            ->where('mentionable_id', $message->id)
            ->where('mentioned_user_id', $userId)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Get mention statistics for a user
     * 
     * @param int $userId
     * @return array
     */
    public function getMentionStats(int $userId): array
    {
        $total = MessageMention::forUser($userId)->count();
        $unread = MessageMention::forUser($userId)->unread()->count();
        $today = MessageMention::forUser($userId)->whereDate('created_at', today())->count();
        
        return [
            'total_mentions' => $total,
            'unread_mentions' => $unread,
            'mentions_today' => $today,
        ];
    }

    /**
     * Check if user has permission to mention in a group
     * 
     * @param int $userId
     * @param int $groupId
     * @return bool
     */
    public function canMentionInGroup(int $userId, int $groupId): bool
    {
        // User must be a member of the group to mention others
        return DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->exists();
    }
}
