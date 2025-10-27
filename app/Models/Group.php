<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Group extends Model
{
    protected $fillable = [
        'name',
        'owner_id',
        'description',
        'avatar_path',
        'is_public',
        'invite_code',
        'slug',
        'type'
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    protected $appends = [
        'unread_count', // Make unread_count available in JSON
        'avatar_url',
        'invite_url'
    ];

    protected static function booted()
    {
        static::creating(function (Group $group) {
            if (empty($group->slug)) {
                $group->slug = $group->generateSlug();
            }
            
            if ($group->type === 'group' && empty($group->invite_code)) {
                $group->invite_code = Str::random(10);
            }
            
            if ($group->type === 'channel') {
                $group->invite_code = null;
            }
        });

        static::updating(function (Group $group) {
            if ($group->isDirty('name')) {
                $group->slug = $group->generateSlug();
            }
        });
    }

    public function generateSlug(): string
    {
        $baseSlug = Str::slug($this->name);
        $randomSuffix = Str::lower(Str::random(5));
        
        $slug = "{$baseSlug}-{$randomSuffix}";
        
        $counter = 1;
        while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $randomSuffix = Str::lower(Str::random(5));
            $slug = "{$baseSlug}-{$randomSuffix}";
            $counter++;
        }

        return $slug;
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $group = $this->where('slug', $value)->first();
        
        if (!$group) {
            $group = $this->where('id', $value)->first();
        }
        
        return $group;
    }

    public function getUrlAttribute(): string
    {
        return route('groups.show', $this->slug);
    }

    public function getInviteUrlAttribute(): ?string
    {
        if ($this->type === 'group' && $this->invite_code) {
            return route('groups.join', $this->invite_code);
        }
        return null;
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar_path
            ? asset('storage/'.$this->avatar_path)
            : asset('images/default-group-avatar.png');
    }

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
     | Unread Count Methods (UNIFIED APPROACH)
     |------------------------------------------------------------------*/

    /**
     * ✅ PRIMARY METHOD: Get unread count for current user
     * Uses GroupMessageStatus table for accurate tracking
     */
    public function getUnreadCountAttribute(): int
    {
        if (!Auth::check()) return 0;
        
        return $this->messages()
            ->where('sender_id', '!=', Auth::id())
            ->whereDoesntHave('statuses', function ($query) {
                $query->where('user_id', Auth::id())
                      ->where('status', GroupMessageStatus::STATUS_READ);
            })
            ->count();
    }

    /**
     * ✅ Get unread count for specific user
     */
    public function getUnreadCountForUser(int $userId): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->whereDoesntHave('statuses', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->where('status', GroupMessageStatus::STATUS_READ);
            })
            ->count();
    }

    /**
     * ✅ Mark all messages as read for a user
     * Uses the status-based approach for consistency
     */
    public function markAsReadForUser(int $userId): int
    {
        if (!$this->isMember($userId)) return 0;

        $unreadMessages = $this->messages()
            ->where('sender_id', '!=', $userId)
            ->whereDoesntHave('statuses', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->where('status', GroupMessageStatus::STATUS_READ);
            })
            ->get();

        $markedCount = 0;
        foreach ($unreadMessages as $message) {
            $message->markAsReadFor($userId);
            $markedCount++;
        }

        return $markedCount;
    }

    /**
     * ✅ Quick check if user has unread messages
     */
    public function hasUnreadMessages(int $userId): bool
    {
        return $this->getUnreadCountForUser($userId) > 0;
    }

    /* -----------------------------------------------------------------
     | Membership Methods
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

    public function addMember(User $user, string $role = 'member'): void
    {
        $this->members()->syncWithoutDetaching([
            $user->id => [
                'role' => $role,
                'joined_at' => now(),
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

    public function isJoinable(): bool
    {
        return $this->is_public || $this->type === 'channel';
    }

    /* -----------------------------------------------------------------
     | Scopes
     |------------------------------------------------------------------*/
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopePrivate($query)
    {
        return $query->where('is_public', false);
    }

    public function scopeChannels($query)
    {
        return $query->where('type', 'channel');
    }

    public function scopeGroups($query)
    {
        return $query->where('type', 'group');
    }

    /**
     * ✅ Efficient scope to load groups with unread counts
     */
    public function scopeWithUnreadCounts($query, int $userId = null)
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) return $query;

        return $query->withCount([
            'messages as unread_count' => function ($query) use ($userId) {
                $query->where('sender_id', '!=', $userId)
                      ->whereDoesntHave('statuses', function ($q) use ($userId) {
                          $q->where('user_id', $userId)
                            ->where('status', GroupMessageStatus::STATUS_READ);
                      });
            }
        ]);
    }

    /**
     * ✅ Scope to only include groups with unread messages
     */
    public function scopeWithUnreadMessages($query, int $userId = null)
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) return $query;

        return $query->whereHas('messages', function ($query) use ($userId) {
            $query->where('sender_id', '!=', $userId)
                  ->whereDoesntHave('statuses', function ($q) use ($userId) {
                      $q->where('user_id', $userId)
                        ->where('status', GroupMessageStatus::STATUS_READ);
                  });
        });
    }
    /**
 * Debug method to check unread counts
 */
public function debugUnreadCounts(int $userId = null): array
{
    $userId = $userId ?? Auth::id();
    
    return [
        'group_id' => $this->id,
        'group_name' => $this->name,
        'user_id' => $userId,
        'unread_count_attribute' => $this->unread_count,
        'unread_count_method' => $this->getUnreadCountForUser($userId),
        'total_messages' => $this->messages()->count(),
        'messages_from_others' => $this->messages()->where('sender_id', '!=', $userId)->count(),
        'read_messages' => $this->messages()
            ->whereHas('statuses', function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->where('status', GroupMessageStatus::STATUS_READ);
            })->count(),
    ];
}
// In Group model
public function generateInviteCode()
{
    do {
        $code = Str::random(10);
    } while (static::where('invite_code', $code)->exists());
    
    return $code;
}

// public function isMember($userId)
// {
//     return $this->members()->where('user_id', $userId)->exists();
// }

public function getInviteLink()
{
    return route('groups.join', $this->invite_code);
}
}