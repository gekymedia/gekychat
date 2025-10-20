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
        'is_public', // Changed from is_private
        'invite_code',
        'slug',
        'type'
    ];

    protected $casts = [
        'is_public' => 'boolean', // Changed from is_private
    ];

    /* -----------------------------------------------------------------
     | Model Events & Slug Generation
     |------------------------------------------------------------------*/
    protected static function booted()
    {
        static::creating(function (Group $group) {
            // Generate unique slug
            if (empty($group->slug)) {
                $group->slug = $group->generateSlug();
            }
            
            // Generate invite code for private groups
            if ($group->type === 'group' && empty($group->invite_code)) {
                $group->invite_code = Str::random(10);
            }
            
            // Public channels don't need invite codes
            if ($group->type === 'channel') {
                $group->invite_code = null;
            }
        });

        static::updating(function (Group $group) {
            // Regenerate slug if name changed
            if ($group->isDirty('name')) {
                $group->slug = $group->generateSlug();
            }
        });
    }

    /**
     * Generate unique slug with random suffix
     */
    public function generateSlug(): string
    {
        $baseSlug = Str::slug($this->name);
        $randomSuffix = Str::lower(Str::random(5)); // 5-character random suffix
        
        $slug = "{$baseSlug}-{$randomSuffix}";
        
        // Ensure uniqueness
        $counter = 1;
        while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $randomSuffix = Str::lower(Str::random(5));
            $slug = "{$baseSlug}-{$randomSuffix}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Get the route key for the model (for pretty URLs)
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * Get the public URL for this group/channel
     */
    public function getUrlAttribute(): string
    {
        if ($this->type === 'channel') {
            return route('groups.show', $this->slug);
        } else {
            return route('groups.show', $this->slug); // Both use slugs now
        }
    }

    /**
     * Get the invite link for private groups
     */
    public function getInviteUrlAttribute(): ?string
    {
        if ($this->type === 'group' && $this->invite_code) {
            return route('groups.join', $this->invite_code);
        }
        return null;
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
     * Compute unread counts for a given user.
     */
    public function unreadCountFor(int $userId): int
    {
        // Prefer per-user status table if present
        if (class_exists(\App\Models\GroupMessageStatus::class)) {
            return $this->messages()
                ->where('sender_id', '!=', $userId)
                ->visibleTo($userId)
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

    /**
     * Check if this group can be joined publicly
     */
    public function isJoinable(): bool
    {
        return $this->is_public || $this->type === 'channel';
    }
    
}