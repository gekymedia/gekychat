<?php

namespace App\Models;

use App\Models\Traits\HasPerUserStatuses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        'sender_type', // 'user', 'platform', or 'email'
        'platform_client_id', // For platform messages
        'user_api_key_id', // For tracking which API key was used
        'body',
        'type', 
        'reply_to',
        'forwarded_from_id',
        'forward_chain',
        'is_encrypted',
        'expires_at',
        'deleted_for_everyone_at', // PHASE 1: Delete for everyone timestamp
        'metadata', // JSON field for additional data
        'location_data', // JSON field for shared location data
        'contact_data', // JSON field for shared contact data
        'call_data', // JSON field for call data
        'mention_count', // Number of @mentions in this message
        // ✅ REMOVED: read_at, delivered_at (now using message_statuses table)
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
        'metadata'      => 'array',
        // ✅ REMOVED: read_at, delivered_at casts
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'location_data' => 'array',
        'contact_data'  => 'array',
        'call_data'     => 'array',
        'edited_at'     => 'datetime',
        'deleted_for_everyone_at' => 'datetime', // PHASE 1: Delete for everyone timestamp
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
        'statuses', // ✅ ADDED: Load statuses for real-time updates
        'emailMessage', // PHASE 2: Load email metadata
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
        'status', // ✅ This will now use message_statuses
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
        // For platform messages, sender_id is null, so return null
        if ($this->sender_type === 'platform' && $this->sender_id === null) {
            return null;
        }
        
        return $this->belongsTo(User::class, 'sender_id')->withDefault([
            'name'       => 'Deleted User',
            'phone'      => '0000000000',
            'avatar_url' => asset('images/default-avatar.png'),
        ]);
    }

    /**
     * Get the API client that sent this message (for platform messages)
     */
    public function platformClient()
    {
        return $this->belongsTo(ApiClient::class, 'platform_client_id');
    }

    /**
     * Get the user API key that was used to send this message
     */
    public function userApiKey()
    {
        return $this->belongsTo(UserApiKey::class, 'user_api_key_id');
    }

    /**
     * PHASE 2: Email message metadata (if this message came from email)
     */
    public function emailMessage()
    {
        return $this->hasOne(EmailMessage::class);
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
     * Get mentions in this message
     */
    public function mentions(): MorphMany
    {
        return $this->morphMany(MessageMention::class, 'mentionable');
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
     * Scope to only unread messages for current user.
     */
    public function scopeUnread(Builder $q)
    {
        if (!Auth::check()) {
            return $q;
        }

        return $q->whereDoesntHave('statuses', function ($query) {
            $query->where('user_id', Auth::id())
                  ->where('status', MessageStatus::STATUS_READ);
        });
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
    /**
     * Hide messages that have been soft‑deleted for a specific user. Uses
     * MessageStatus.deleted_at to track per‑user deletions.
     * 
     * PHASE 1: Also hides messages deleted for everyone (unless saved messages conversation).
     */
    public function scopeVisibleTo(Builder $q, int $userId)
    {
        return $q->where(function ($subQ) {
            // PHASE 1: Hide messages deleted for everyone (unless saved messages - handled in controller)
            $subQ->whereNull('deleted_for_everyone_at')
                ->orWhere('deleted_for_everyone_at', '>', now()); // Allow if somehow future-dated
        })
        ->whereDoesntHave('statuses', function ($s) use ($userId) {
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
     * Compute status based on message_statuses for current user.
     */
    public function getStatusAttribute(): string
    {
        if (!Auth::check()) {
            return MessageStatus::STATUS_SENT;
        }

        $userStatus = $this->statuses->where('user_id', Auth::id())->first();
        
        return $userStatus ? $userStatus->status : MessageStatus::STATUS_SENT;
    }

    /**
     * When the message was read by a recipient (non-sender).
     * Derived from message_statuses: latest READ status by user_id != sender_id.
     * Used by API (MessageResource) so clients get read_at for sent messages.
     */
    public function getReadAtAttribute(): ?\Illuminate\Support\Carbon
    {
        if ($this->relationLoaded('statuses')) {
            $read = $this->statuses
                ->where('user_id', '!=', $this->sender_id)
                ->where('status', MessageStatus::STATUS_READ)
                ->whereNull('deleted_at')
                ->sortByDesc('updated_at')
                ->first();
            return $read ? $read->updated_at : null;
        }
        $row = $this->statuses()
            ->where('user_id', '!=', $this->sender_id)
            ->where('status', MessageStatus::STATUS_READ)
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->first();
        return $row ? $row->updated_at : null;
    }

    /**
     * When the message was delivered to a recipient (non-sender).
     * Derived from message_statuses: latest DELIVERED or READ by user_id != sender_id.
     */
    public function getDeliveredAtAttribute(): ?\Illuminate\Support\Carbon
    {
        if ($this->relationLoaded('statuses')) {
            $delivered = $this->statuses
                ->where('user_id', '!=', $this->sender_id)
                ->whereIn('status', [MessageStatus::STATUS_DELIVERED, MessageStatus::STATUS_READ])
                ->whereNull('deleted_at')
                ->sortByDesc('updated_at')
                ->first();
            return $delivered ? $delivered->updated_at : null;
        }
        $row = $this->statuses()
            ->where('user_id', '!=', $this->sender_id)
            ->whereIn('status', [MessageStatus::STATUS_DELIVERED, MessageStatus::STATUS_READ])
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->first();
        return $row ? $row->updated_at : null;
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

    /**
     * Get link previews from message body.
     */
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

    /*
     |--------------------------------------------------------------------------
     | Status Helpers
     |--------------------------------------------------------------------------
     */

    /**
     * Mark the message as delivered for a specific user.
     */
    public function markAsDeliveredFor(int $userId): void
    {
        try {
            $this->statuses()->updateOrCreate(
                ['message_id' => $this->id, 'user_id' => $userId],
                ['status' => MessageStatus::STATUS_DELIVERED]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle duplicate entry errors gracefully
            if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                // Record already exists, try to update it
                try {
                    $this->statuses()
                        ->where('message_id', $this->id)
                        ->where('user_id', $userId)
                        ->update(['status' => MessageStatus::STATUS_DELIVERED]);
                } catch (\Exception $updateException) {
                    \Log::warning('Failed to update message status after duplicate entry: ' . $updateException->getMessage());
                }
            } else {
                \Log::error('Failed to mark message as delivered: ' . $e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * Mark the message as read for a specific user.
     */
    public function markAsReadFor(int $userId): void
    {
        try {
            $this->statuses()->updateOrCreate(
                ['message_id' => $this->id, 'user_id' => $userId],
                ['status' => MessageStatus::STATUS_READ]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle duplicate entry errors gracefully
            if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                // Record already exists, try to update it
                try {
                    $this->statuses()
                        ->where('message_id', $this->id)
                        ->where('user_id', $userId)
                        ->update(['status' => MessageStatus::STATUS_READ]);
                } catch (\Exception $updateException) {
                    \Log::warning('Failed to update message status after duplicate entry: ' . $updateException->getMessage());
                }
            } else {
                \Log::error('Failed to mark message as read: ' . $e->getMessage());
                throw $e;
            }
        }
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
     * Mark the message as delivered for the current authenticated user.
     */
    public function markAsDelivered(): void
    {
        if (!Auth::check()) return;
        $this->markAsDeliveredFor(Auth::id());
    }

    /**
     * Check if the message belongs to a specific user.
     */
    public function isOwnedBy(int $userId): bool
    {
        return (int) $this->sender_id === (int) $userId;
    }

    /**
     * Messages that reply to this message.
     */
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
public function getIsReadAttribute(): bool
{
    return ($this->status ?? MessageStatus::STATUS_SENT) === MessageStatus::STATUS_READ;
}

    /*
     |--------------------------------------------------------------------------
     | Model Events
     |--------------------------------------------------------------------------
     */
    protected static function booted(): void
    {
        static::deleting(function (Message $message) {
            // Delete attachments, reactions and statuses when a message is hard deleted
            $message->attachments()->delete();
            $message->reactions()->delete();
            $message->statuses()->delete();
        });
    }
}