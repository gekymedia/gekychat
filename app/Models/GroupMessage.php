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
        // ❌ removed: 'read_at',
        // ❌ removed: 'delivered_at',
        'edited_at',
        'deleted_for_user_id',
        'client_uuid',
        'location_data', // JSON field for shared location data
        'contact_data', // JSON field for shared contact data
        'call_data', // JSON field for call data
    ];

    protected $casts = [
        'forward_chain' => 'array',
        // ❌ removed: 'read_at'    => 'datetime',
        // ❌ removed: 'delivered_at' => 'datetime',
        'edited_at'     => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'location_data' => 'array',
        'contact_data'  => 'array',
        'call_data'     => 'array',
    ];

    protected $with = [
        'sender',
        'attachments',
        'replyTo',
        'forwardedFrom',
        'reactions.user',
         'statuses', // 👈 add this
    ];

    protected $appends = [
        'is_own',
        'time_ago',
        'is_forwarded',
        'display_body',
        'is_read', // stays, but now 100% driven by statuses
        'link_previews', // Add link previews for group messages
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

    // ✅ readers derived from statuses (no read_at column)
    public function readers()
    {
        return $this->hasMany(GroupMessageStatus::class, 'group_message_id')
            ->where('status', GroupMessageStatus::STATUS_READ)
            ->with('user:id,name,avatar_path');
    }

    /* -------------------------
     | Scopes
     * ------------------------*/

    public function scopeVisibleTo(Builder $q, int $userId)
    {
        return $q->where(function ($q) use ($userId) {
            $q->whereNull('deleted_for_user_id')
              ->orWhere('deleted_for_user_id', '!=', $userId);
        });
    }

    public function scopeUnreadForUser(Builder $q, int $userId)
    {
        return $q->where('sender_id', '!=', $userId)
            ->whereDoesntHave('statuses', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->where('status', GroupMessageStatus::STATUS_READ);
            });
    }

    public function scopeDeletedForUser(Builder $q, int $userId)
    {
        return $q->where('deleted_for_user_id', $userId);
    }

    public function scopeWithStatusCounters(Builder $q)
    {
        return $q->withCount([
            'statuses as read_count' => fn ($s) =>
                $s->where('status', GroupMessageStatus::STATUS_READ),

            'statuses as read_count_others' => fn ($s) =>
                $s->where('status', GroupMessageStatus::STATUS_READ)
                  ->whereColumn('group_message_statuses.user_id', '!=', 'group_messages.sender_id'),

            'statuses as delivered_count' => fn ($s) =>
                $s->whereIn('status', [
                    GroupMessageStatus::STATUS_DELIVERED,
                    GroupMessageStatus::STATUS_READ,
                ]),
        ]);
    }

    /* -------------------------
     | Accessors (Appends)
     * ------------------------*/

    public function getIsOwnAttribute(): bool
    {
        return Auth::check() && (int) $this->sender_id === (int) Auth::id();
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

    /** ✅ Now purely based on statuses */
    public function getIsReadAttribute(): bool
    {
        if (!Auth::check()) return false;

        return $this->statuses()
            ->where('user_id', Auth::id())
            ->where('status', GroupMessageStatus::STATUS_READ)
            ->exists();
    }

    public function getIsDeletedForMeAttribute(): bool
    {
        return Auth::check() && $this->deleted_for_user_id === Auth::id();
    }

    /* -------------------------
     | Status helpers
     * ------------------------*/

    public function markAsReadFor(int $userId): void
    {
        if ($userId === (int) $this->sender_id) {
            return;
        }

        // ✅ only update statuses
        $this->statuses()->updateOrCreate(
            ['user_id' => $userId],
            [
                'status'     => GroupMessageStatus::STATUS_READ,
                'updated_at' => now(),
            ]
        );
    }

    public function markAsDeliveredFor(int $userId): void
    {
        // ✅ only update statuses
        $this->statuses()->updateOrCreate(
            ['user_id' => $userId],
            [
                'status'     => GroupMessageStatus::STATUS_DELIVERED,
                'updated_at' => now(),
            ]
        );
    }

    public function isReadBy(int $userId): bool
    {
        return $this->statuses()
            ->where('user_id', $userId)
            ->where('status', GroupMessageStatus::STATUS_READ)
            ->exists();
    }

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

    public function deleteForUser(int $userId): void
    {
        $this->update(['deleted_for_user_id' => $userId]);

        $this->statuses()->updateOrCreate(
            ['user_id' => $userId],
            ['deleted_at' => now(), 'updated_at' => now()]
        );
    }

    public function restoreForUser(int $userId): void
    {
        if ($this->deleted_for_user_id === $userId) {
            $this->update(['deleted_for_user_id' => null]);

            $this->statuses()
                ->where('user_id', $userId)
                ->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);
        }
    }

    public function buildForwardChain(): array
    {
        $chain  = is_array($this->forward_chain) ? $this->forward_chain : [];
        $origin = $this->forwardedFrom ?: $this;

        array_unshift($chain, [
            'id'           => $origin->id,
            'sender'       => optional($origin->sender)->name
                              ?? optional($origin->sender)->phone
                              ?? 'User',
            'body'         => Str::limit((string) ($origin->body ?? ''), 100),
            'timestamp'    => optional($origin->created_at)?->toIso8601String(),
            'is_encrypted' => (bool) ($origin->is_encrypted ?? false),
            'source'       => 'group',
        ]);

        return $chain;
    }

    public function canDelete(int $userId): bool
    {
        if ($this->sender_id === $userId) {
            return true;
        }

        $group = $this->group;
        if ($group) {
            $member = $group->members()->where('user_id', $userId)->first();
            return $member && in_array($member->pivot->role, ['admin', 'owner']);
        }

        return false;
    }

    public function canEdit(int $userId): bool
    {
        return $this->sender_id === $userId;
    }

 protected static function booted(): void
{
    static::deleting(function (GroupMessage $message) {
        // Only permanently delete attachments if this is a force delete
        if ($message->isForceDeleting()) {
            $message->attachments()
                ->get()
                ->each(function ($attachment) {
                    $attachment->delete();
                });

            $message->reactions()->delete();
            $message->statuses()->delete();
        }
    });
//     static::deleting(function (GroupMessage $message) {
//     if ($message->isForceDeleting()) {
//         ...
//     }
// });

}

}
