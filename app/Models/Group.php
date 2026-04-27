<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Group extends Model
{
    use SoftDeletes;
    
    /**
     * Maximum number of members allowed in a group (5,000 - double WhatsApp's limit)
     * Channels have no limit as they are broadcast-only
     */
    public const MAX_GROUP_MEMBERS = 5000;
    
    protected $fillable = [
        'name',
        'owner_id',
        'description',
        'avatar_path',
        'is_public',
        'invite_code',
        'slug',
        'call_id', // Unique call ID for this group
        'type',
        'is_verified',
        'message_lock', // Only admins can send when enabled
        'require_approval', // Require admin approval to join via invite link
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_verified' => 'boolean',
        'message_lock' => 'boolean',
        'require_approval' => 'boolean',
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
            
            // Generate unique call_id for groups (not channels)
            if ($group->type !== 'channel' && empty($group->call_id)) {
                $group->call_id = $group->generateCallId();
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
            return route('groups.join-via-invite', $this->invite_code);
        }
        return null;
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar_path
            ? \App\Helpers\UrlHelper::secureAsset('storage/'.$this->avatar_path)
            : \App\Helpers\UrlHelper::secureAsset('images/group-default.png');
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
            ->withPivot(['role', 'joined_at', 'pinned_at', 'muted_until'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GroupMessage::class);
    }

    /**
     * PHASE 2: Channel posts (only for channels, not regular groups)
     */
    public function channelPosts(): HasMany
    {
        return $this->hasMany(ChannelPost::class, 'channel_id');
    }

    /**
     * PHASE 2: Channel followers (only for channels)
     */
    public function channelFollowers(): HasMany
    {
        return $this->hasMany(ChannelFollower::class, 'channel_id');
    }

    /**
     * Join requests for this group (when require_approval is enabled)
     */
    public function joinRequests(): HasMany
    {
        return $this->hasMany(GroupJoinRequest::class);
    }

    /**
     * Pending join requests for this group
     */
    public function pendingJoinRequests(): HasMany
    {
        return $this->hasMany(GroupJoinRequest::class)->where('status', GroupJoinRequest::STATUS_PENDING);
    }

    /**
     * Check if a user has a pending join request
     */
    public function hasPendingRequestFrom(int $userId): bool
    {
        return $this->joinRequests()
            ->where('user_id', $userId)
            ->where('status', GroupJoinRequest::STATUS_PENDING)
            ->exists();
    }

    /**
     * PHASE 2: Check if user follows this channel
     */
    public function isFollowedBy(int $userId): bool
    {
        if ($this->type !== 'channel') {
            return false;
        }
        return $this->channelFollowers()->where('user_id', $userId)->exists();
    }

    public function admins(): BelongsToMany
    {
        return $this->members()->wherePivot('role', 'admin');
    }

    /**
     * Labels assigned to this group (for labeled lists filter).
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'group_label')->withTimestamps();
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
        if (array_key_exists('unread_count', $this->attributes)) {
            return (int) $this->attributes['unread_count'];
        }
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
     * Alias for markAsReadForUser() for consistency with API calls
     */
    public function markAllAsReadForUser(int $userId): int
    {
        return $this->markAsReadForUser($userId);
    }

    /**
     * ✅ Quick check if user has unread messages
     */
    public function hasUnreadMessages(int $userId): bool
    {
        return $this->getUnreadCountForUser($userId) > 0;
    }

    /**
     * Mark all messages in the group as unread for the given user.
     * Deletes read statuses so getUnreadCountForUser will count them again.
     * POST /groups/{id}/mark-unread
     */
    public function markAsUnreadForUser(int $userId): int
    {
        if (!$this->isMember($userId)) {
            return 0;
        }
        $messageIds = $this->messages()->pluck('id');
        $deleted = GroupMessageStatus::query()
            ->where('user_id', $userId)
            ->whereIn('group_message_id', $messageIds)
            ->where('status', GroupMessageStatus::STATUS_READ)
            ->delete();
        return $deleted;
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

    /**
     * Check if the group can accept more members
     * Channels have no limit, groups are limited to MAX_GROUP_MEMBERS
     */
    public function canAddMembers(int $count = 1): bool
    {
        // Channels have no member limit
        if ($this->type === 'channel') {
            return true;
        }
        
        $currentCount = $this->members()->count();
        return ($currentCount + $count) <= self::MAX_GROUP_MEMBERS;
    }

    /**
     * Get remaining member slots for the group
     */
    public function getRemainingSlots(): int
    {
        // Channels have unlimited slots
        if ($this->type === 'channel') {
            return PHP_INT_MAX;
        }
        
        $currentCount = $this->members()->count();
        return max(0, self::MAX_GROUP_MEMBERS - $currentCount);
    }

    public function addMember(User $user, string $role = 'member', bool $createSystemMessage = true): void
    {
        $wasMember = $this->isMember($user);
        
        $this->members()->syncWithoutDetaching([
            $user->id => [
                'role' => $role,
                'joined_at' => now(),
            ]
        ]);
        
        // Create system message when user joins (if not already a member and flag is true)
        if (!$wasMember && $createSystemMessage) {
            $this->createSystemMessage('joined', $user->id);
        }
    }
    
    /**
     * Create a system message in the group
     * For channels, skip "joined" and "left" messages to avoid spam
     */
    public function createSystemMessage(string $action, ?int $userId = null, array $metadata = []): ?GroupMessage
    {
        // Skip join/leave messages for channels - they would be too noisy
        if ($this->type === 'channel' && in_array($action, ['joined', 'left'])) {
            return null;
        }
        
        $isChannel = $this->type === 'channel';
        $messages = [
            'joined' => $isChannel ? 'joined the channel' : 'joined the group',
            'left' => $isChannel ? 'left the channel' : 'left the group',
            'promoted' => 'was promoted to admin',
            'demoted' => 'was removed from admin',
            'removed' => $isChannel ? 'was removed from the channel' : 'was removed from the group',
        ];
        
        $user = $userId ? \App\Models\User::find($userId) : null;
        $body = $user 
            ? "{$user->name} " . ($messages[$action] ?? $action)
            : ($messages[$action] ?? $action);
        
        return $this->messages()->create([
            'sender_id' => $userId ?? $this->owner_id, // Use user ID or owner as fallback
            'is_system' => true,
            'system_action' => $action,
            'body' => $body,
        ]);
    }

    public function removeMember(User $user, bool $createSystemMessage = true): void
    {
        $wasMember = $this->isMember($user);
        $this->members()->detach($user->id);
        
        // Create system message when user is removed (if they were a member)
        if ($wasMember && $createSystemMessage) {
            $this->createSystemMessage('removed', $user->id);
        }
    }

    public function promoteToAdmin(User $user, bool $createSystemMessage = true): void
    {
        $this->members()->updateExistingPivot($user->id, [
            'role' => 'admin'
        ]);
        
        // Create system message when user is promoted
        if ($createSystemMessage) {
            $this->createSystemMessage('promoted', $user->id);
        }
    }
    
    public function demoteFromAdmin(User $user, bool $createSystemMessage = true): void
    {
        $this->members()->updateExistingPivot($user->id, [
            'role' => 'member'
        ]);
        
        // Create system message when user is demoted
        if ($createSystemMessage) {
            $this->createSystemMessage('demoted', $user->id);
        }
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
        // Use the correct route name
        return route('groups.join-via-invite', $this->invite_code);
    }

    /**
     * Generate a unique call ID for this group
     */
    public function generateCallId(): string
    {
        do {
            $callId = Str::random(16);
        } while (static::where('call_id', $callId)->exists());

        return $callId;
    }

    /**
     * Get or generate the call ID for this group
     */
    public function getOrGenerateCallId(): string
    {
        // Channels don't support calls
        if ($this->type === 'channel') {
            throw new \Exception('Channels do not support calls');
        }

        if (empty($this->call_id)) {
            $this->call_id = $this->generateCallId();
            $this->save();
        }

        return $this->call_id;
    }

    /**
     * Get the call link URL for this group
     */
    public function getCallLinkAttribute(): string
    {
        $callId = $this->getOrGenerateCallId();
        $path = route('calls.join', $callId, false);
        $base = config('app.web_url') ?: config('app.url');
        return rtrim($base, '/') . $path;
    }
}
