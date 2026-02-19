<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * ✅ MODERN: Redis caching service for high-performance data access
 * WhatsApp/Telegram-style caching for unread counts and metadata
 * Reduces database load and improves response times
 */
class RedisCacheService
{
    private const TTL_UNREAD_COUNT = 3600; // 1 hour
    private const TTL_LAST_MESSAGE = 3600; // 1 hour
    private const TTL_CONVERSATION_META = 7200; // 2 hours
    private const TTL_USER_PROFILE = 86400; // 24 hours
    
    /**
     * Get unread count for a user in a conversation
     */
    public function getUnreadCount(int $userId, int $conversationId): ?int
    {
        try {
            $key = "unread:{$userId}:{$conversationId}";
            $count = Redis::get($key);
            return $count !== null ? (int)$count : null;
        } catch (\Exception $e) {
            Log::warning('Redis getUnreadCount failed', [
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    
    /**
     * Set unread count for a user in a conversation
     */
    public function setUnreadCount(int $userId, int $conversationId, int $count): void
    {
        try {
            $key = "unread:{$userId}:{$conversationId}";
            Redis::setex($key, self::TTL_UNREAD_COUNT, $count);
        } catch (\Exception $e) {
            Log::warning('Redis setUnreadCount failed', [
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'count' => $count,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Increment unread count for a user in a conversation
     */
    public function incrementUnreadCount(int $userId, int $conversationId): void
    {
        try {
            $key = "unread:{$userId}:{$conversationId}";
            Redis::incr($key);
            Redis::expire($key, self::TTL_UNREAD_COUNT);
        } catch (\Exception $e) {
            Log::warning('Redis incrementUnreadCount failed', [
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Reset unread count for a user in a conversation
     */
    public function resetUnreadCount(int $userId, int $conversationId): void
    {
        try {
            $key = "unread:{$userId}:{$conversationId}";
            Redis::del($key);
        } catch (\Exception $e) {
            Log::warning('Redis resetUnreadCount failed', [
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Get last message ID for a conversation
     */
    public function getLastMessageId(int $conversationId): ?int
    {
        try {
            $key = "last_msg:{$conversationId}";
            $id = Redis::get($key);
            return $id !== null ? (int)$id : null;
        } catch (\Exception $e) {
            Log::warning('Redis getLastMessageId failed', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    
    /**
     * Set last message ID for a conversation
     */
    public function setLastMessageId(int $conversationId, int $messageId): void
    {
        try {
            $key = "last_msg:{$conversationId}";
            Redis::setex($key, self::TTL_LAST_MESSAGE, $messageId);
        } catch (\Exception $e) {
            Log::warning('Redis setLastMessageId failed', [
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Get conversation metadata
     */
    public function getConversationMetadata(int $conversationId): ?array
    {
        try {
            $key = "conv_meta:{$conversationId}";
            $data = Redis::get($key);
            return $data ? json_decode($data, true) : null;
        } catch (\Exception $e) {
            Log::warning('Redis getConversationMetadata failed', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    
    /**
     * Set conversation metadata
     */
    public function setConversationMetadata(int $conversationId, array $metadata): void
    {
        try {
            $key = "conv_meta:{$conversationId}";
            Redis::setex($key, self::TTL_CONVERSATION_META, json_encode($metadata));
        } catch (\Exception $e) {
            Log::warning('Redis setConversationMetadata failed', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Get user profile from cache
     */
    public function getUserProfile(int $userId): ?array
    {
        try {
            $key = "user_profile:{$userId}";
            $data = Redis::get($key);
            return $data ? json_decode($data, true) : null;
        } catch (\Exception $e) {
            Log::warning('Redis getUserProfile failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    
    /**
     * Set user profile in cache
     */
    public function setUserProfile(int $userId, array $profile): void
    {
        try {
            $key = "user_profile:{$userId}";
            Redis::setex($key, self::TTL_USER_PROFILE, json_encode($profile));
        } catch (\Exception $e) {
            Log::warning('Redis setUserProfile failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Invalidate conversation cache
     */
    public function invalidateConversation(int $conversationId): void
    {
        try {
            $keys = [
                "conv_meta:{$conversationId}",
                "last_msg:{$conversationId}",
            ];
            Redis::del($keys);
        } catch (\Exception $e) {
            Log::warning('Redis invalidateConversation failed', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Invalidate user cache
     */
    public function invalidateUser(int $userId): void
    {
        try {
            Redis::del("user_profile:{$userId}");
        } catch (\Exception $e) {
            Log::warning('Redis invalidateUser failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Get total unread count for a user across all conversations
     */
    public function getTotalUnreadCount(int $userId): ?int
    {
        try {
            $key = "total_unread:{$userId}";
            $count = Redis::get($key);
            return $count !== null ? (int)$count : null;
        } catch (\Exception $e) {
            Log::warning('Redis getTotalUnreadCount failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    
    /**
     * Set total unread count for a user
     */
    public function setTotalUnreadCount(int $userId, int $count): void
    {
        try {
            $key = "total_unread:{$userId}";
            Redis::setex($key, self::TTL_UNREAD_COUNT, $count);
        } catch (\Exception $e) {
            Log::warning('Redis setTotalUnreadCount failed', [
                'user_id' => $userId,
                'count' => $count,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
