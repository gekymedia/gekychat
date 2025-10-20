<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasPushSubscriptions;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'otp_code',
        'otp_expires_at',
        'is_admin',
        'phone_verified_at',
        'two_factor_code',
        'two_factor_expires_at',
        'avatar_path',
        'slug',
        'last_seen_at', // Added for online status
        'status', // Added for user status (online, away, etc.)
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
        'two_factor_code',
    ];

    protected $casts = [
        'email_verified_at'     => 'datetime',
        'phone_verified_at'     => 'datetime',
        'otp_expires_at'        => 'datetime',
        'two_factor_expires_at' => 'datetime',
        'last_seen_at'          => 'datetime',
        'is_admin'              => 'boolean',
    ];

    protected $appends = [
        'avatar_url',
        'is_online',
        'initial',
    ];

    /* ================================================================
     | Model Events
     * ================================================================*/
    protected static function booted()
    {
        static::creating(function ($user) {
            if (empty($user->slug)) {
                $user->slug = $user->generateSlug();
            }
            
            // Set initial last_seen_at
            if (empty($user->last_seen_at)) {
                $user->last_seen_at = now();
            }
        });

        static::updating(function ($user) {
            if ($user->isDirty('name')) {
                $user->slug = $user->generateSlug();
            }
        });
    }

    /* ================================================================
     | OTP & 2FA helpers
     * ================================================================*/
    public function generateOtp(int $digits = 6, int $ttlMinutes = 5): string
    {
        $max  = (10 ** $digits) - 1;
        $code = str_pad((string) random_int(0, $max), $digits, '0', STR_PAD_LEFT);

        $this->forceFill([
            'otp_code'       => $code,
            'otp_expires_at' => now()->addMinutes($ttlMinutes),
        ])->save();

        return $code;
    }

    public function clearOtp(): void
    {
        $this->forceFill([
            'otp_code'       => null,
            'otp_expires_at' => null,
        ])->save();
    }

    public function generateTwoFactorCode(int $digits = 6, int $ttlMinutes = 10): string
    {
        $max  = (10 ** $digits) - 1;
        $code = str_pad((string) random_int(0, $max), $digits, '0', STR_PAD_LEFT);

        $this->forceFill([
            'two_factor_code'       => $code,
            'two_factor_expires_at' => now()->addMinutes($ttlMinutes),
        ])->save();

        return $code;
    }

    public function clearTwoFactorCode(): void
    {
        $this->forceFill([
            'two_factor_code'       => null,
            'two_factor_expires_at' => null,
        ])->save();
    }

    public function hasVerifiedPhone(): bool
    {
        return !is_null($this->phone_verified_at);
    }

    public function markPhoneAsVerified(): void
    {
        $this->forceFill(['phone_verified_at' => now()])->save();
    }

    /* ================================================================
     | Relationships
     * ================================================================*/

    /** Updated: Pivot-based conversations (matches new Conversation model) */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_user')
            ->withPivot(['role', 'last_read_message_id', 'muted_until', 'pinned_at'])
            ->withTimestamps()
            ->latest('updated_at');
    }

    /** Legacy: Keep for backward compatibility */
    public function conversationsAsUserOne(): HasMany
    {
        return $this->hasMany(Conversation::class, 'user_one_id');
    }

    public function conversationsAsUserTwo(): HasMany
    {
        return $this->hasMany(Conversation::class, 'user_two_id');
    }

    /** Updated: Use pivot-based query */
    public function conversationsQuery()
    {
        return $this->conversations();
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /** Files the user has uploaded (if you keep user->attachments); optional. */
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Groups */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot(['role', 'joined_at', 'last_read_message_id'])
            ->withTimestamps()
            ->latest('updated_at');
    }

    public function groupMessages(): HasMany
    {
        return $this->hasMany(GroupMessage::class, 'sender_id');
    }

    /** Reactions */
    public function messageReactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function groupMessageReactions(): HasMany
    {
        return $this->hasMany(GroupMessageReaction::class);
    }

    public function ownedGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'owner_id');
    }

    /** Contacts Relationships */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'user_id');
    }

    public function contactOf(): HasMany
    {
        return $this->hasMany(Contact::class, 'contact_user_id');
    }

    /** Get users who have this user in their contacts */
    public function addedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'contacts', 'contact_user_id', 'user_id')
            ->withTimestamps();
    }

    /** Check if this user is in another user's contacts */
    public function isInContactsOf(User $user): bool
    {
        return $user->contacts()->where('contact_user_id', $this->id)->exists();
    }

    /** Check if another user is in this user's contacts */
    public function hasInContacts(User $user): bool
    {
        return $this->contacts()->where('contact_user_id', $user->id)->exists();
    }

    /** Get mutual contacts between two users */
    public function mutualContactsWith(User $user)
    {
        $myContacts = $this->contacts()->pluck('contact_user_id');
        $theirContacts = $user->contacts()->pluck('contact_user_id');
        
        return $myContacts->intersect($theirContacts);
    }

    /* ================================================================
     | Helpers (authz & convenience)
     * ================================================================*/

    public function isGroupAdmin(Group|int $group): bool
    {
        $groupId = is_object($group) ? $group->id : $group;

        return $this->groups()
            ->where('group_id', $groupId)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function isGroupOwner(Group|int $group): bool
    {
        $groupId = is_object($group) ? $group->id : $group;

        return $this->ownedGroups()->where('id', $groupId)->exists();
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar_path) {
            return Storage::disk('public')->exists($this->avatar_path) 
                ? Storage::disk('public')->url($this->avatar_path)
                : $this->generateDefaultAvatar();
        }
        
        return $this->generateDefaultAvatar();
    }

    public function getInitialAttribute(): string
    {
        return strtoupper(substr($this->name ?: 'U', 0, 1));
    }

    public function getIsOnlineAttribute(): bool
    {
        return $this->last_seen_at && 
               $this->last_seen_at->gt(now()->subMinutes(5));
    }

    public function getLastSeenForHumansAttribute(): string
    {
        if ($this->is_online) {
            return 'Online';
        }
        
        return $this->last_seen_at 
            ? $this->last_seen_at->diffForHumans()
            : 'Never';
    }

    /** Updated: Use pivot-based check */
    public function canJoinConversation(int $conversationId): bool
    {
        return $this->conversations()->where('conversations.id', $conversationId)->exists();
    }

    /** Check membership by group id efficiently. */
    public function belongsToGroup(int $groupId): bool
    {
        return $this->groups()->where('groups.id', $groupId)->exists();
    }

    /** Updated: Use pivot-based check */
    public function isParticipantOfConversation(Conversation|int $conversation): bool
    {
        $id = is_object($conversation) ? $conversation->id : $conversation;
        return $this->conversations()->where('conversations.id', $id)->exists();
    }

    /**
     * Update last seen timestamp
     */
    public function updateLastSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->save();
    }

    /**
     * Generate unique slug for user
     */
    public function generateSlug(): string
    {
        $baseSlug = Str::slug($this->name ?: 'user');
        $slug = $baseSlug;
        $counter = 1;

        while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $slug = $baseSlug . '-' . $counter;
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
     * Get the public URL for this user's profile
     */
    public function getProfileUrlAttribute(): string
    {
        return route('profile.show', $this);
    }

    /**
     * Get the direct chat URL for this user
     */
    public function getDirectChatUrlAttribute(): string
    {
        return route('direct.chat', $this->slug);
    }

    /**
     * Find user by slug or ID (flexible lookup)
     */
    public static function findBySlugOrId($identifier)
    {
        return static::where('slug', $identifier)
            ->orWhere('id', $identifier)
            ->first();
    }

    /**
     * Find user by phone number (with normalization)
     */
    public static function findByPhone($phone)
    {
        $normalizedPhone = Contact::normalizePhone($phone);
        
        if (empty($normalizedPhone)) {
            return null;
        }

        return static::where('phone', $normalizedPhone)->first();
    }

    /**
     * Get user's saved messages conversation
     */
    public function savedMessages(): ?Conversation
    {
        return Conversation::savedMessages($this->id)->first();
    }

    /**
     * Get or create saved messages conversation
     */
    public function getOrCreateSavedMessages(): Conversation
    {
        return Conversation::findOrCreateSavedMessages($this->id);
    }

    /**
     * Check if user has unread messages in a conversation
     */
    public function hasUnreadMessagesInConversation(Conversation $conversation): bool
    {
        return $conversation->unreadCountFor($this->id) > 0;
    }

    /**
     * Check if user has unread messages in a group
     */
    public function hasUnreadMessagesInGroup(Group $group): bool
    {
        return $group->unreadCountFor($this->id) > 0;
    }

    /**
     * Get all unread conversations count
     */
    public function getTotalUnreadConversationsCount(): int
    {
        return $this->conversations()
            ->get()
            ->filter(fn($conv) => $conv->unreadCountFor($this->id) > 0)
            ->count();
    }

    /**
     * Get all unread groups count
     */
    public function getTotalUnreadGroupsCount(): int
    {
        return $this->groups()
            ->get()
            ->filter(fn($group) => $group->unreadCountFor($this->id) > 0)
            ->count();
    }

    /**
     * Get total unread messages count across all conversations and groups
     */
    public function getTotalUnreadCount(): int
    {
        $conversationUnread = $this->conversations()
            ->get()
            ->sum(fn($conv) => $conv->unreadCountFor($this->id));

        $groupUnread = $this->groups()
            ->get()
            ->sum(fn($group) => $group->unreadCountFor($this->id));

        return $conversationUnread + $groupUnread;
    }

    /**
     * Get user's display name for contacts
     */
    public function getDisplayNameFor(User $viewer): string
    {
        // If the viewer has this user in contacts with a custom display name
        $contact = $viewer->contacts()->where('contact_user_id', $this->id)->first();
        
        return $contact?->display_name ?: $this->name;
    }

    /**
     * Search users by name, phone, or email
     */
    public static function search($query)
    {
        return static::where('name', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->where('id', '!=', auth()->id()) // Exclude current user
            ->limit(50)
            ->get();
    }

    /**
     * Get users who are not in contacts
     */
    public function getNonContactUsers()
    {
        $contactUserIds = $this->contacts()->pluck('contact_user_id');
        
        return static::whereNotIn('id', $contactUserIds)
            ->where('id', '!=', $this->id)
            ->get();
    }

    /**
     * Generate default avatar URL
     */
    protected function generateDefaultAvatar(): string
    {
        // You can use a service like DiceBear or generate initials-based avatar
        $initial = $this->initial;
        $backgroundColor = $this->generateColorFromName();
        
        return "https://ui-avatars.com/api/?name={$initial}&background={$backgroundColor}&color=fff&size=128";
    }

    /**
     * Generate consistent color from user's name
     */
    protected function generateColorFromName(): string
    {
        $colors = [
            'f44336', 'e91e63', '9c27b0', '673ab7', '3f51b5',
            '2196f3', '03a9f4', '00bcd4', '009688', '4caf50',
            '8bc34a', 'cddc39', 'ffeb3b', 'ffc107', 'ff9800',
        ];
        
        $hash = crc32($this->name ?: 'user');
        return $colors[$hash % count($colors)];
    }

    /**
     * Scope for online users
     */
    public function scopeOnline($query)
    {
        return $query->where('last_seen_at', '>', now()->subMinutes(5));
    }

    /**
     * Scope for offline users
     */
    public function scopeOffline($query)
    {
        return $query->where(function($q) {
            $q->whereNull('last_seen_at')
              ->orWhere('last_seen_at', '<=', now()->subMinutes(5));
        });
    }

    /**
     * Scope for registered users (phone verified)
     */
    public function scopeRegistered($query)
    {
        return $query->whereNotNull('phone_verified_at');
    }

    /**
     * Get user's privacy settings (you might want to create a separate settings model)
     */
    public function getPrivacySettings(): array
    {
        return [
            'show_online_status' => true, // Default
            'show_last_seen' => true, // Default
            'allow_direct_chat' => true, // Default
            'show_phone_number' => false, // Default
        ];
    }
}