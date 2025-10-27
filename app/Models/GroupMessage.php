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
        'reply_to',
        'forwarded_from_id',
        'forward_chain',
        'read_at',
        'delivered_at',
        'edited_at',
        'deleted_for_user_id',
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
        'display_body',
        'is_read', // Add this for frontend
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

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function replyTo()
    {
        return $this->belongsTo(self::class, 'reply_to')->withDefault();
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

    // ✅ FIXED: Single readers relationship using statuses
    public function readers()
    {
        return $this->hasMany(GroupMessageStatus::class, 'group_message_id')
            ->where('status', GroupMessageStatus::STATUS_READ)
            ->with('user:id,name,avatar_path');
    }

    /* -------------------------
     | Scopes
     * ------------------------*/

    /** ✅ FIXED: Scope to show only messages not deleted for user */
    public function scopeVisibleTo(Builder $q, int $userId)
    {
        return $q->where(function($q) use ($userId) {
            $q->whereNull('deleted_for_user_id')
              ->orWhere('deleted_for_user_id', '!=', $userId);
        });
    }

    /** Scope for unread messages for a specific user */
    public function scopeUnreadForUser(Builder $q, int $userId)
    {
        return $q->where('sender_id', '!=', $userId)
            ->whereDoesntHave('statuses', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->where('status', GroupMessageStatus::STATUS_READ);
            });
    }

    /** Scope to get messages deleted for a specific user */
    public function scopeDeletedForUser(Builder $q, int $userId)
    {
        return $q->where('deleted_for_user_id', $userId);
    }

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
        if ($this->deleted_for_user_id && $this->deleted_for_user_id == Auth::id()) {
            return '[Message deleted]';
        }
        return (string)($this->body ?? '');
    }

    /** ✅ NEW: Check if current user has read this message */
    public function getIsReadAttribute(): bool
    {
        if (!Auth::check()) return false;
        
        return $this->statuses()
            ->where('user_id', Auth::id())
            ->where('status', GroupMessageStatus::STATUS_READ)
            ->exists();
    }

    /** Check if message is deleted for current user */
    public function getIsDeletedForMeAttribute(): bool
    {
        return Auth::check() && $this->deleted_for_user_id === Auth::id();
    }

    /* -------------------------
     | Status helpers
     * ------------------------*/
    public function markAsReadFor(int $userId): void
    {
        // Don't mark own messages as read
        if ($userId === (int)$this->sender_id) {
            return;
        }

        // Update status
        $this->statuses()->updateOrCreate(
            ['user_id' => $userId],
            [
                'status' => GroupMessageStatus::STATUS_READ, 
                'updated_at' => now()
            ]
        );

        // Also update the read_at timestamp for quick queries
        if (is_null($this->read_at)) {
            $this->forceFill(['read_at' => now()])->save();
        }
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

    /** Check if user has read this message */
    public function isReadBy(int $userId): bool
    {
        return $this->statuses()
            ->where('user_id', $userId)
            ->where('status', GroupMessageStatus::STATUS_READ)
            ->exists();
    }

    /** Get unread count for a specific user in a group */
    public static function getUnreadCountForUser(int $groupId, int $userId): int
    {
        return self::where('group_id', $groupId)
            ->where('sender_id', '!=', $userId)
            ->whereDoesntHave('statuses', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->where('status', GroupMessageStatus::STATUS_READ);
            })
            ->count();
    }

    /** Soft hide for a specific user ("delete for me") */
    public function deleteForUser(int $userId): void
    {
        $this->update(['deleted_for_user_id' => $userId]);
        
        // Also update status for consistency
        $this->statuses()->updateOrCreate(
            ['user_id' => $userId],
            ['deleted_at' => now(), 'updated_at' => now()]
        );
    }

    /** Restore message for a user */
    public function restoreForUser(int $userId): void
    {
        if ($this->deleted_for_user_id === $userId) {
            $this->update(['deleted_for_user_id' => null]);
            
            // Remove deletion status
            $this->statuses()
                ->where('user_id', $userId)
                ->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);
        }
    }

    public function buildForwardChain(): array
    {
        $chain = is_array($this->forward_chain) ? $this->forward_chain : [];

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

    /** Check if user can delete this message */
    public function canDelete(int $userId): bool
    {
        // User can delete their own messages
        if ($this->sender_id === $userId) {
            return true;
        }

        // Check if user is group admin or owner
        $group = $this->group;
        if ($group) {
            $member = $group->members()->where('user_id', $userId)->first();
            return $member && in_array($member->pivot->role, ['admin', 'owner']);
        }

        return false;
    }

    /** Check if user can edit this message */
    public function canEdit(int $userId): bool
    {
        // Only sender can edit their own messages
        return $this->sender_id === $userId;
    }

    /* -------------------------
     | Cascades
     * ------------------------*/
    protected static function booted(): void
    {
        static::deleting(function (GroupMessage $message) {
            // Only permanently delete attachments if this is a force delete
            if ($message->isForceDeleting()) {
                $message->attachments()->each->delete();
                $message->reactions()->delete();
                $message->statuses()->delete();
            }
        });
    }
}