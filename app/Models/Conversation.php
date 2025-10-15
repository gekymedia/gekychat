<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class Conversation extends Model
{
    protected $fillable = [
        'is_group',        // bool
        'name',            // group name (null for 1:1)
        'avatar_path',     // optional group avatar
        'description',     // optional group description
        'is_private',      // optional (bool) if you support private groups
        'created_by',      // user id
        'invite_code',     // optional if you generate invite links
    ];

    protected $casts = [
        'is_group'   => 'boolean',
        'is_private' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Keep these so the web views/mobile summaries can use them easily
    protected $appends = [
        'unread_count',
        'other_user',   // User or null (for 1:1 only)
        'title',        // string
        'avatar_url',   // string|null
    ];

    // Eager-load the latest message (cheap and commonly shown)
    protected $with = ['latestMessage'];

    /* -------------------------
     | Relationships
     * ------------------------*/

    public function members(): BelongsToMany
    {
        // Pivot: conversation_user (conversation_id, user_id, role, last_read_message_id, muted_until, pinned_at, timestamps)
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'last_read_message_id', 'muted_until', 'pinned_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id', 'asc');
    }

    // Back-compat: many blades expect "latestMessage"
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    // Optional alias (some code prefers lastMessage)
    public function lastMessage(): HasOne
    {
        return $this->latestMessage();
    }

    /* -------------------------
     | Scopes
     * ------------------------*/

    public function scopeDirect(Builder $q): Builder
    {
        return $q->where('is_group', false);
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->whereHas('members', fn($m) => $m->where('users.id', $userId));
    }

    /**
     * Filter direct chats that contain both users $a and $b.
     */
    public function scopeBetweenUsers(Builder $q, int $a, int $b): Builder
    {
        return $q->direct()
            ->whereHas('members', fn($m) => $m->where('users.id', $a))
            ->whereHas('members', fn($m) => $m->where('users.id', $b))
            // Optional: enforce exactly 2 members
            ->whereHas('members', fn($m) => $m, '=', 2);
    }

    /* -------------------------
     | Helpers
     * ------------------------*/

    /**
     * Create (if needed) a deterministic 1:1 conversation between two user IDs.
     */
    public static function findOrCreateDirect(int $a, int $b, ?int $createdBy = null): self
    {
        if ($a === $b) {
            // Prevent self-DM; you can change this to allow "Saved Messages" style
            abort(422, 'Cannot start a direct chat with yourself.');
        }

        $existing = static::query()->betweenUsers($a, $b)->first();
        if ($existing) {
            return $existing;
        }

        $conv = static::create([
            'is_group'   => false,
            'name'       => null,
            'created_by' => $createdBy ?? $a,
        ]);

        $conv->members()->syncWithPivotValues([$a, $b], ['role' => 'member']);

        return $conv->fresh(['members', 'latestMessage']);
    }

    /**
     * Check if a given user participates in this conversation.
     */
    public function isParticipant(int $userId): bool
    {
        if ($this->relationLoaded('members')) {
            return $this->members->contains('id', $userId);
        }
        return $this->members()->where('users.id', $userId)->exists();
    }

    /**
     * Mark messages as read for a user. If $messageId is null, mark up to latest.
     */
    public function markRead(int $userId, ?int $messageId = null): void
    {
        if (!$this->isParticipant($userId)) return;

        $lastId = $messageId ?? (int) ($this->messages()->max('id') ?: 0);
        $this->members()->updateExistingPivot($userId, ['last_read_message_id' => $lastId]);
    }

    /**
     * Unread count for a specific user, using the pivot's last_read_message_id.
     * Excludes messages sent by that user.
     */
    public function unreadCountFor(int $userId): int
    {
        $pivot = $this->members()->where('users.id', $userId)->first()?->pivot;
        $lastReadId = (int) ($pivot?->last_read_message_id ?? 0);

        return $this->messages()
            ->where('id', '>', $lastReadId)
            ->where('sender_id', '!=', $userId)
            ->count();
    }

    /**
     * For a direct chat, return the other participant relative to $userId (or current auth user).
     */
    public function otherParticipant(?int $userId = null): ?User
    {
        if ($this->is_group) return null;

        $uid = $userId ?? Auth::id();
        if (!$uid) return null;

        // Ensure members are available
        $members = $this->relationLoaded('members') ? $this->members : $this->members()->get();
        return $members->firstWhere('id', '!=', $uid);
    }

    /* -------------------------
     | Accessors (appends)
     * ------------------------*/

    public function getUnreadCountAttribute(): int
    {
        $userId = Auth::id();
        if (!$userId) return 0;
        return $this->unreadCountFor($userId);
    }

    /**
     * Back-compat for older views expecting $conversation->other_user (1:1 only).
     */
    public function getOtherUserAttribute(): ?User
    {
        return $this->otherParticipant();
    }

    /**
     * A human title for listing: group name or the other user's name/phone.
     */
    public function getTitleAttribute(): string
    {
        if ($this->is_group) {
            return (string) ($this->name ?? 'Group');
        }

        $other = $this->otherParticipant();
        return $other?->name ?: ($other?->phone ?: 'Unknown');
    }

    /**
     * A display avatar URL: group avatar or the other user's avatar (if any).
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->is_group) {
            return $this->avatar_path ? Storage::url($this->avatar_path) : null;
        }

        $other = $this->otherParticipant();
        if ($other?->avatar_path) {
            return Storage::url($other->avatar_path);
        }
        return null;
    }
}


    // protected $fillable = [
    //     'is_group',
    //     'name',
    //     'avatar_path',   // <— match DB column
    //     'created_by',
    // ];

    // protected $casts = [
    //     'is_group' => 'boolean',
    // ];

    // public function members()
    // {
    //     return $this->belongsToMany(User::class)
    //         ->withPivot(['role','last_read_message_id','muted_until','pinned_at'])
    //         ->withTimestamps();
    // }

    // public function messages()
    // {
    //     return $this->hasMany(Message::class);
    // }

    // public function lastMessage()
    // {
    //     return $this->hasOne(Message::class)->latestOfMany();
    // }

    // // Find or create deterministic direct chat (fills pivot)
    // public static function findOrCreateDirect(int $a, int $b): self
    // {
    //     $ids = [$a, $b];
    //     sort($ids);

    //     $existing = self::query()
    //         ->where('is_group', false)
    //         ->whereHas('members', fn($q) => $q->whereKey($ids[0]))
    //         ->whereHas('members', fn($q) => $q->whereKey($ids[1]))
    //         ->first();

    //     if ($existing) return $existing;

    //     $conv = self::create([
    //         'is_group'   => false,
    //         'name'       => null,
    //         'created_by' => $a,
    //     ]);

    //     $conv->members()->syncWithPivotValues($ids, ['role' => 'member']);

    //     return $conv->fresh();
    // }

