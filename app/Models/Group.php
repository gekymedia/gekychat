<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Group extends Model
{
    protected $fillable = [
        'name',
        'owner_id',
        'description',
        'avatar_path',
        'is_private',
        'invite_code'
    ];

    protected $casts = [
        'is_private' => 'boolean',
    ];

    /* -----------------------------------------------------------------
     | Relationships
     |------------------------------------------------------------------*/
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GroupMessage::class);
    }

    public function admins(): BelongsToMany
    {
        return $this->members()->wherePivot('role', 'admin');
    }

    /* -----------------------------------------------------------------
     | Helper Methods
     |------------------------------------------------------------------*/
    public function isMember(User|int $user): bool
    {
        $userId = is_object($user) ? $user->id : $user;
        return $this->members()->where('user_id', $userId)->exists();
    }

    public function isAdmin(User|int $user): bool
    {
        $userId = is_object($user) ? $user->id : $user;
        return $this->admins()->where('user_id', $userId)->exists();
    }

    public function isOwner(User|int $user): bool
    {
        $userId = is_object($user) ? $user->id : $user;
        return (int)$this->owner_id === (int)$userId;
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar_path
            ? asset('storage/'.$this->avatar_path)
            : asset('images/default-group-avatar.png');
    }

    /* -----------------------------------------------------------------
     | Model Events
     |------------------------------------------------------------------*/
    protected static function booted()
    {
        static::creating(function (Group $group) {
            $group->invite_code = $group->invite_code ?? Str::random(10);
        });
    }

    /* -----------------------------------------------------------------
     | Scopes
     |------------------------------------------------------------------*/
    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    public function scopePrivate($query)
    {
        return $query->where('is_private', true);
    }

    /**
     * Compute unread counts for a given user.
     * - If GroupMessageStatus model exists (per-user read tracking), use it.
     * - Otherwise fall back to group_messages.read_at.
     */
    public function unreadCountFor(int $userId): int
    {
        // Prefer per-user status table if present
        if (class_exists(\App\Models\GroupMessageStatus::class)) {
            return $this->messages()
                ->where('sender_id', '!=', $userId)
                ->visibleTo($userId)
                // not read by this user according to statuses
                ->whereDoesntHave('statuses', function ($s) use ($userId) {
                    $s->where('user_id', $userId)
                      ->where('status', \App\Models\GroupMessageStatus::STATUS_READ);
                })
                ->count();
        }

        // Fallback: simple read_at column
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->visibleTo($userId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Attach unread_count to the group query for a given user.
     * Works with or without per-user status table.
     */
    public function scopeWithUnreadCountFor(\Illuminate\Database\Eloquent\Builder $q, int $userId)
    {
        if (class_exists(\App\Models\GroupMessageStatus::class)) {
            return $q->withCount([
                'messages as unread_count' => function ($m) use ($userId) {
                    $m->where('sender_id', '!=', $userId)
                      ->visibleTo($userId)
                      ->whereDoesntHave('statuses', function ($s) use ($userId) {
                          $s->where('user_id', $userId)
                            ->where('status', \App\Models\GroupMessageStatus::STATUS_READ);
                      });
                }
            ]);
        }

        // Fallback to read_at
        return $q->withCount([
            'messages as unread_count' => function ($m) use ($userId) {
                $m->where('sender_id', '!=', $userId)
                  ->visibleTo($userId)
                  ->whereNull('read_at');
            }
        ]);
    }

    /* -----------------------------------------------------------------
     | Membership Management
     |------------------------------------------------------------------*/
    public function addMember(User $user, string $role = 'member'): void
    {
        $this->members()->syncWithoutDetaching([
            $user->id => [
                'role' => $role,
                'joined_at' => now()
            ]
        ]);
    }

    public function removeMember(User $user): void
    {
        $this->members()->detach($user->id);
    }

    public function promoteToAdmin(User $user): void
    {
        $this->members()->updateExistingPivot($user->id, [
            'role' => 'admin'
        ]);
    }
}
