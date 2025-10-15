<?php

namespace App\Models;

use App\Models\Traits\HasPerUserStatuses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GroupMessage extends Model
{
    use HasFactory, HasPerUserStatuses;

    /** Tell the trait which status model to use */
    public static function statusClass(): string
    {
        return \App\Models\GroupMessageStatus::class;
    }

    protected $table = 'group_messages';

    protected $fillable = [
        'group_id',
        'sender_id',
        'body',
        'reply_to_id',
        'forwarded_from_id',
        'forward_chain',
        'read_at',
        'delivered_at',
        'edited_at',
        // allow clients to supply a uuid for idempotency; nullable
        'client_uuid',
    ];

    protected $casts = [
        'forward_chain' => 'array',
        'read_at'       => 'datetime',
        'delivered_at'  => 'datetime',
        'edited_at'     => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    // What the chat UI typically needs in one go
    protected $with = [
        'sender',
        'attachments',
        'replyTo',
        'forwardedFrom',
        'reactions.user',
    ];

    protected $appends = [
        'is_own',
        'time_ago',
        'is_forwarded',
        'display_body',   // small DX nicety for blades that expect it
    ];

    /* -------------------------
     | Relationships
     * ------------------------*/
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id')->withDefault([
            'name'  => 'Unknown User',
            'phone' => '0000000000',
        ]);
    }

    // POLYMORPHIC attachments (attachable_id / attachable_type)
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function replyTo()
    {
        return $this->belongsTo(self::class, 'reply_to_id')->withDefault();
    }

    public function forwardedFrom()
    {
        return $this->belongsTo(self::class, 'forwarded_from_id')->withDefault();
    }

    public function reactions()
    {
        return $this->hasMany(GroupMessageReaction::class, 'group_message_id');
    }

    public function statuses()
    {
        return $this->hasMany(GroupMessageStatus::class, 'group_message_id');
    }

    /** Everyone who read this message (+ user info) */
    public function readers()
    {
        return $this->hasMany(GroupMessageStatus::class, 'group_message_id')
            ->where('status', GroupMessageStatus::STATUS_READ)
            ->with(['user:id,name,avatar_path']);
    }

    /* -------------------------
     | Scopes
     * ------------------------*/

    /** Per-user visibility (“delete for me”) using statuses.deleted_at */
    public function scopeVisibleTo(Builder $q, int $userId)
    {
        return $q->whereDoesntHave('statuses', function ($s) use ($userId) {
            $s->where('user_id', $userId)->whereNotNull('deleted_at');
        });
    }

    /** Handy counters if you need badges in UI */
    public function scopeWithStatusCounters(Builder $q)
    {
        return $q->withCount([
            'statuses as read_count' => fn($s) =>
                $s->where('status', GroupMessageStatus::STATUS_READ),

            'statuses as read_count_others' => fn($s) =>
                $s->where('status', GroupMessageStatus::STATUS_READ)
                  ->whereColumn('group_message_statuses.user_id', '!=', 'group_messages.sender_id'),

            'statuses as delivered_count' => fn($s) =>
                $s->whereIn('status', [
                    GroupMessageStatus::STATUS_DELIVERED,
                    GroupMessageStatus::STATUS_READ
                ]),
        ]);
    }

    /* -------------------------
     | Accessors (Appends)
     * ------------------------*/
    public function getIsOwnAttribute(): bool
    {
        return Auth::check() && (int)$this->sender_id === (int)Auth::id();
    }

    public function getTimeAgoAttribute(): string
    {
        return optional($this->created_at)->diffForHumans() ?? '';
    }

    public function getIsForwardedAttribute(): bool
    {
        return !is_null($this->forwarded_from_id);
    }

    public function getDisplayBodyAttribute(): string
    {
        return (string)($this->body ?? '');
    }

    /* -------------------------
     | Status helpers
     * ------------------------*/
    public function markAsReadFor(int $userId): void
    {
        if ($userId !== (int)$this->sender_id && is_null($this->read_at)) {
            $this->forceFill(['read_at' => now()])->save();
        }

        $this->statuses()->updateOrCreate(
            ['user_id' => $userId],
            ['status' => GroupMessageStatus::STATUS_READ, 'updated_at' => now()]
        );
    }

    public function markAsDeliveredFor(int $userId): void
    {
        if ($userId !== (int)$this->sender_id && is_null($this->delivered_at)) {
            $this->forceFill(['delivered_at' => now()])->save();
        }

        $this->statuses()->updateOrCreate(
            ['user_id' => $userId],
            ['status' => GroupMessageStatus::STATUS_DELIVERED, 'updated_at' => now()]
        );
    }

    /** Soft hide for a specific user (“delete for me”) */
    public function deleteForUser(int $userId): void
    {
        $this->statuses()->updateOrCreate(
            ['user_id' => $userId],
            ['deleted_at' => now(), 'updated_at' => now()]
        );
    }

    /**
     * Forward chain:
     * - If this message was already forwarded, prepend its source.
     * - If it wasn’t, still include *this* message as the first entry.
     *   (This makes first-time forwards include an origin in the chain.)
     */
    public function buildForwardChain(): array
    {
        $chain = is_array($this->forward_chain) ? $this->forward_chain : [];

        // Use the original if present; otherwise include self as the origin.
        $origin = $this->forwardedFrom ?: $this;

        array_unshift($chain, [
            'id'           => $origin->id,
            'sender'       => optional($origin->sender)->name
                              ?? optional($origin->sender)->phone
                              ?? 'User',
            'body'         => Str::limit((string)($origin->body ?? ''), 100),
            'timestamp'    => optional($origin->created_at)?->toIso8601String(),
            'is_encrypted' => (bool)($origin->is_encrypted ?? false),
            'source'       => 'group',
        ]);

        return $chain;
    }

    /* -------------------------
     | Cascades
     * ------------------------*/
    protected static function booted(): void
    {
        static::deleting(function (GroupMessage $message) {
            // Delete DB rows; file unlink is handled in Attachment model's deleting() (recommended)
            $message->attachments()->each->delete();
            $message->reactions()->delete();
            $message->statuses()->delete();
        });
    }
}
