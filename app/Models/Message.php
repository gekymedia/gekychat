<?php

namespace App\Models;

use App\Models\Traits\HasPerUserStatuses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The Message model represents a single direct message between two users.  It
 * supports replies, forwards, encryption, message expiry and per‑user
 * read/delivered statuses.  The additional columns required for these
 * features are added via migrations (see the 2025_10_14 migration).
 */
class Message extends Model
{
    use HasFactory, HasPerUserStatuses;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_uuid',
        'conversation_id',
        'sender_id',
        'body',
        'type', 
        'reply_to',
        'forwarded_from_id',
        'forward_chain',
        'is_encrypted',
        'expires_at',
        'read_at',
        'delivered_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'forward_chain' => 'array',
        'is_encrypted'  => 'boolean',
        'expires_at'    => 'datetime',
        'read_at'       => 'datetime',
        'delivered_at'  => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    /**
     * Eager load relationships commonly needed by the chat UI.
     *
     * @var array<int, string>
     */
    protected $with = [
        'sender',
        'attachments',
        'replyTo',
        'forwardedFrom',
        'reactions.user',
    ];

    /**
     * Accessors appended to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'is_own',
        'time_ago',
        'is_forwarded',
        'status',
        'is_expired',
        'display_body',
    ];

    /**
     * Return the status class name for the HasPerUserStatuses trait.
     */
    public static function statusClass(): string
    {
        return MessageStatus::class;
    }

    /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */

    /**
     * The conversation this message belongs to.
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * The user who sent the message. If the user has been deleted, provide
     * sensible defaults so the UI does not break.
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id')->withDefault([
            'name'       => 'Deleted User',
            'phone'      => '0000000000',
            'avatar_url' => asset('images/default-avatar.png'),
        ]);
    }

// Add this method to your Message model
public function getLinkPreviewsAttribute(): array
{
    if (empty($this->body)) {
        return [];
    }

    $previews = [];
    $pattern = '/(https?:\/\/[^\s]+)/';
    
    preg_match_all($pattern, $this->body, $matches);
    
    if (!empty($matches[0])) {
        $linkPreviewService = app(\App\Services\LinkPreviewService::class);
        
        foreach ($matches[0] as $url) {
            $preview = $linkPreviewService->getPreview($url);
            if ($preview) {
                $previews[] = $preview;
            }
        }
    }
    
    return $previews;
}
    /**
     * Attachments associated with this message. Uses a polymorphic relation
     * because attachments can belong to direct messages or group messages.
     */
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * The message this one is replying to, if any.
     */
    public function replyTo()
    {
        return $this->belongsTo(self::class, 'reply_to')->withDefault([
            'body' => '[Deleted Message]',
        ]);
    }

    /**
     * The original message that was forwarded. Null when this message is not a
     * forward.
     */
    public function forwardedFrom()
    {
        return $this->belongsTo(self::class, 'forwarded_from_id')->withDefault();
    }

    /**
     * Reactions associated with this message.
     */
    public function reactions()
    {
        return $this->hasMany(MessageReaction::class)->with('user');
    }

    /**
     * Per‑user status rows for this message.
     */
    public function statuses()
    {
        return $this->hasMany(MessageStatus::class);
    }

    /**
     * Readers of this message, including the user id and basic info. Uses
     * statuses to derive read counts.
     */
    public function readers()
    {
        return $this->hasMany(MessageStatus::class)
            ->where('status', MessageStatus::STATUS_READ)
            ->with(['user:id,name,avatar_path']);
    }

    /*
     |--------------------------------------------------------------------------
     | Scopes
     |--------------------------------------------------------------------------
     */

    /**
     * Scope to only unread messages.
     */
    public function scopeUnread(Builder $q)
    {
        return $q->whereNull('read_at');
    }

    /**
     * Scope messages belonging to a particular user (either as sender or
     * recipient via the conversation pivot).
     */
    public function scopeForUser(Builder $q, int $userId)
    {
        return $q->whereHas('conversation', function ($c) use ($userId) {
            $c->where('user_one_id', $userId)->orWhere('user_two_id', $userId);
        });
    }

    /**
     * Scope messages that have not expired.
     */
    public function scopeNotExpired(Builder $q)
    {
        return $q->where(function ($w) {
            $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Hide messages that have been soft‑deleted for a specific user. Uses
     * MessageStatus.deleted_at to track per‑user deletions.
     */
    public function scopeVisibleTo(Builder $q, int $userId)
    {
        return $q->whereDoesntHave('statuses', function ($s) use ($userId) {
            $s->where('user_id', $userId)->whereNotNull('deleted_at');
        });
    }

    /**
     * Eager‑load counters for read and delivered statuses.  This is useful
     * when displaying message lists with badges.
     */
    public function scopeWithStatusCounters(Builder $q)
    {
        return $q->withCount([
            // readers (including sender if they created a status row)
            'statuses as read_count' => fn ($s) => $s->where('status', MessageStatus::STATUS_READ),

            // readers excluding the sender
            'statuses as read_count_others' => function ($s) {
                $s->where('status', MessageStatus::STATUS_READ)
                  ->whereColumn('message_statuses.user_id', '!=', 'messages.sender_id');
            },

            // delivered OR read (one row per user)
            'statuses as delivered_count' => fn ($s) =>
                $s->whereIn('status', [MessageStatus::STATUS_DELIVERED, MessageStatus::STATUS_READ]),
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | Accessors
     |--------------------------------------------------------------------------
     */

    /**
     * Whether the current authenticated user sent the message.
     */
    public function getIsOwnAttribute(): bool
    {
        return Auth::check() && ((int) $this->sender_id === (int) Auth::id());
    }

    /**
     * Human‑friendly timestamp for the UI.
     */
    public function getTimeAgoAttribute(): string
    {
        return optional($this->created_at)->diffForHumans() ?? '';
    }

    /**
     * Compute a simple status based on global read/delivered timestamps.
     */
    public function getStatusAttribute(): string
    {
        if ($this->read_at)       return MessageStatus::STATUS_READ;
        if ($this->delivered_at)  return MessageStatus::STATUS_DELIVERED;
        return MessageStatus::STATUS_SENT;
    }

    /**
     * Whether this message is a forward.
     */
    public function getIsForwardedAttribute(): bool
    {
        return !is_null($this->forwarded_from_id);
    }

    /**
     * Whether the message has expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        return (bool) ($this->expires_at && $this->expires_at->isPast());
    }

    /**
     * A displayable version of the body.  If the message is encrypted and the
     * current user is not the sender, hide the contents.
     */
    public function getDisplayBodyAttribute(): string
    {
        if ($this->is_encrypted && !$this->is_own) {
            return '[Encrypted Message]';
        }
        return (string) ($this->body ?? '');
    }

    /*
     |--------------------------------------------------------------------------
     | Status Helpers
     |--------------------------------------------------------------------------
     */

    /**
     * Mark the message as delivered for a specific user.  Also update the
     * global delivered_at timestamp for convenience.
     */
    public function markAsDeliveredFor(int $userId): void
    {
        if ($userId !== (int) $this->sender_id && is_null($this->delivered_at)) {
            $this->forceFill(['delivered_at' => now()])->save();
        }
        parent::markAsDeliveredFor($userId);
    }

    /**
     * Mark the message as read for a specific user.  Also update the
     * global read_at timestamp for convenience.
     */
    public function markAsReadFor(int $userId): void
    {
        if ($userId !== (int) $this->sender_id && is_null($this->read_at)) {
            $this->forceFill(['read_at' => now()])->save();
        }
        parent::markAsReadFor($userId);
    }

    /**
     * Convenience helper for the current authenticated user.
     */
    public function markAsRead(): void
    {
        if (!Auth::check()) return;
        $this->markAsReadFor(Auth::id());
    }

    /**
     * Mark the message as delivered for the current authenticated user.  If
     * delivered_at is not yet set, update it and also create or update the
     * status row for the recipient.
     */
    public function markAsDelivered(): void
    {
        if (!Auth::check()) return;

        if (is_null($this->delivered_at)) {
            $this->update(['delivered_at' => now()]);

            // Track on recipient row too
            $recipientId = $this->conversation->user_one_id === $this->sender_id
                ? $this->conversation->user_two_id
                : $this->conversation->user_one_id;

            $this->statuses()->updateOrCreate(
                ['user_id' => $recipientId],
                ['status' => MessageStatus::STATUS_DELIVERED, 'updated_at' => now()]
            );
        }
    }

    /**
     * Check if the message belongs to a specific user.
     */
    public function isOwnedBy(int $userId): bool
    {
        return (int) $this->sender_id === (int) $userId;
    }
// In App\Models\Message.php
// public function replyTo()
// {
//     return $this->belongsTo(Message::class, 'reply_to'); // ← This should match your database column
// }

public function repliedBy()
{
    return $this->hasMany(Message::class, 'reply_to');
}
    /**
     * Build a neutral forward chain that captures the history of forwards in
     * a DM‑agnostic format.  Useful when forwarding DMs into groups or vice
     * versa.
     */
    public function buildForwardChain(): array
    {
        if (!$this->forwarded_from_id || !$this->forwardedFrom) {
            return [];
        }

        $from  = $this->forwardedFrom;
        $chain = $from->forward_chain ?? [];

        array_unshift($chain, [
            'id'           => $from->id,
            'sender'       => optional($from->sender)->name ?? optional($from->sender)->phone,
            'body'         => Str::limit($from->display_body ?? '', 100),
            'timestamp'    => optional($from->created_at)?->toIso8601String(),
            'is_encrypted' => (bool) $from->is_encrypted,
            'source'       => 'dm',
        ]);

        return $chain;
    }

    /*
     |--------------------------------------------------------------------------
     | Model Events
     |--------------------------------------------------------------------------
     */
  /*
 |--------------------------------------------------------------------------
 | Model Events
 |--------------------------------------------------------------------------
 */
/*
 |--------------------------------------------------------------------------
 | Model Events
 |--------------------------------------------------------------------------
 */
protected static function booted(): void
{
    static::deleting(function (Message $message) {
        // Delete attachments, reactions and statuses when a message is hard deleted
        // ✅ FIXED: Use delete() on relationships directly
        $message->attachments()->delete();
        $message->reactions()->delete();
        $message->statuses()->delete();
    });
}
}