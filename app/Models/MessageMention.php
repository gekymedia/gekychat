<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MessageMention extends Model
{
    protected $fillable = [
        'mentionable_type',
        'mentionable_id',
        'mentioned_user_id',
        'mentioned_by_user_id',
        'position_start',
        'position_end',
        'is_read',
        'read_at',
        'notification_sent',
        'notification_sent_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'notification_sent' => 'boolean',
        'notification_sent_at' => 'datetime',
    ];

    protected $appends = [
        'message_preview',
    ];

    /**
     * Get the message or group message that contains this mention
     */
    public function mentionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who was mentioned
     */
    public function mentionedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }

    /**
     * Get the user who created the mention
     */
    public function mentionedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_by_user_id');
    }

    /**
     * Mark mention as read
     */
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark notification as sent
     */
    public function markNotificationSent(): void
    {
        $this->update([
            'notification_sent' => true,
            'notification_sent_at' => now(),
        ]);
    }

    /**
     * Get message preview (first 100 chars)
     */
    public function getMessagePreviewAttribute(): ?string
    {
        if (!$this->mentionable) {
            return null;
        }

        $body = $this->mentionable->body ?? '';
        return strlen($body) > 100 ? substr($body, 0, 100) . '...' : $body;
    }

    /**
     * Scope for unread mentions
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for mentions for a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('mentioned_user_id', $userId);
    }

    /**
     * Get all mentions with eager loaded relationships
     */
    public function scopeWithRelations($query)
    {
        return $query->with([
            'mentionable',
            'mentionedUser:id,name,username,avatar_path',
            'mentionedByUser:id,name,username,avatar_path',
        ]);
    }
}
