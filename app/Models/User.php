<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasPushSubscriptions;



    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
        'two_factor_code',
    ];


    protected $appends = [
        'avatar_url',
        'is_online',
        'initial',
        'last_seen_formatted',
        'about_text' // Add this line
    ];
    // In your User model's $fillable array
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar_path',
        'slug',
        'otp_code',
        'otp_expires_at',
        'is_admin',
        'phone_verified_at',
        'two_factor_code',
        'two_factor_expires_at',
        'last_seen_at',
        'status',
        'about',
        'google_access_token', // Add this
        'google_refresh_token', // Add this
        'google_sync_enabled', // Add this
        'last_google_sync_at', // Add this
        'normalized_phone', // Make sure this is in fillable
    ];

    // Add to $casts array
    protected $casts = [
        'email_verified_at'     => 'datetime',
        'phone_verified_at'     => 'datetime',
        'otp_expires_at'        => 'datetime',
        'two_factor_expires_at' => 'datetime',
        'last_seen_at'          => 'datetime',
        'last_google_sync_at'   => 'datetime', // Add this
        'is_admin'              => 'boolean',
        'google_sync_enabled'   => 'boolean', // Add this
    ];

    // Add these methods to your User model
    public function googleContacts()
    {
        return $this->hasMany(GoogleContact::class);
    }

    public function hasGoogleAccess()
    {
        return !empty($this->google_access_token);
    }
    // public function hasGoogleAccess()
    // {
    //     return !empty($this->google_token);
    // }
    public function enableGoogleSync()
    {
        $this->update(['google_sync_enabled' => true]);
    }

    public function disableGoogleSync()
    {
        $this->update(['google_sync_enabled' => false]);
    }

    public function updateLastSyncTime()
    {
        $this->update(['last_google_sync_at' => now()]);
    }
    /* ==================== CORE RELATIONSHIPS ==================== */

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_user')
            ->withPivot(['role', 'last_read_message_id', 'muted_until', 'pinned_at'])
            ->withTimestamps()
            ->latest('updated_at');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot(['role', 'joined_at', 'last_read_message_id'])
            ->withTimestamps()
            ->latest('updated_at');
    }
    /* ==================== ABOUT/STATUS METHODS ==================== */

    public function updateAbout(string $about): bool
    {
        return $this->update(['about' => $about]);
    }

    public function hasCustomAbout(): bool
    {
        return !empty($this->about) && $this->about !== 'Hey there! I am using GekyChat';
    }
    // Add this method to your appends
    public function getAboutTextAttribute(): string
    {
        return $this->about ?? 'Hey there! I am using GekyChat';
    }

    public function getAboutDisplay(): string
    {
        if (empty($this->about)) {
            return 'Hey there! I am using GekyChat';
        }

        return $this->about;
    }
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function groupMessages(): HasMany
    {
        return $this->hasMany(GroupMessage::class, 'sender_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'user_id');
    }

    public function contactOf(): HasMany
    {
        return $this->hasMany(Contact::class, 'contact_user_id');
    }

    public function addedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'contacts', 'contact_user_id', 'user_id')
            ->withTimestamps();
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function messageReactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function groupMessageReactions(): HasMany
    {
        return $this->hasMany(GroupMessageReaction::class);
    }

    // Add this method to your User model (App\Models\User)
    public function isContact($userId)
    {
        return $this->contacts()
            ->where('contact_user_id', $userId)
            ->exists();
    }

    public function getContact($userId)
    {
        return $this->contacts()
            ->where('contact_user_id', $userId)
            ->first();
    }

    public function ownedGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'owner_id');
    }

    /* ==================== REAL-TIME STATUS ==================== */

    public function getIsOnlineAttribute(): bool
    {
        return Cache::has('user-is-online-' . $this->id);
    }

    public function getLastSeenFormattedAttribute(): string
    {
        if ($this->is_online) {
            return 'Online';
        }

        if (!$this->last_seen_at) {
            return 'Never';
        }

        return $this->last_seen_at->diffForHumans();
    }

    public function updateLastSeen(): void
    {
        // Update every 1 minute to avoid too many DB writes
        if (!$this->last_seen_at || $this->last_seen_at->lt(now()->subMinute())) {
            $this->update(['last_seen_at' => now()]);
        }

        // Keep cache updated for real-time online status
        Cache::put('user-is-online-' . $this->id, true, now()->addMinutes(2));
    }

    public function markOnline(): void
    {
        $this->updateLastSeen();
    }

    public function markOffline(): void
    {
        Cache::forget('user-is-online-' . $this->id);
    }

    /* ==================== ESSENTIAL HELPERS ==================== */

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar_path && Storage::disk('public')->exists($this->avatar_path)) {
            return Storage::disk('public')->url($this->avatar_path);
        }
        return $this->generateDefaultAvatar();
    }

    public function getInitialAttribute(): string
    {
        return strtoupper(substr($this->name ?: 'U', 0, 1));
    }

    public function canJoinConversation(int $conversationId): bool
    {
        return $this->conversations()->where('conversations.id', $conversationId)->exists();
    }

    public function belongsToGroup(int $groupId): bool
    {
        return $this->groups()->where('groups.id', $groupId)->exists();
    }

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

    /* ==================== OTP & AUTH ==================== */

    public function generateOtp(int $digits = 6, int $ttlMinutes = 5): string
    {
        $max  = (10 ** $digits) - 1;
        $code = str_pad((string) random_int(0, $max), $digits, '0', STR_PAD_LEFT);

        $this->update([
            'otp_code'       => $code,
            'otp_expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return $code;
    }

    public function clearOtp(): void
    {
        $this->update([
            'otp_code'       => null,
            'otp_expires_at' => null,
        ]);
    }

    public function hasVerifiedPhone(): bool
    {
        return !is_null($this->phone_verified_at);
    }

    public function markPhoneAsVerified(): void
    {
        $this->update(['phone_verified_at' => now()]);
    }

    /* ==================== BOOT & SLUG ==================== */

    protected static function booted()
    {
        static::creating(function ($user) {
            if (empty($user->slug)) {
                $user->slug = $user->generateSlug();
            }
            $user->last_seen_at = now();
        });

        static::updating(function ($user) {
            if ($user->isDirty('name')) {
                $user->slug = $user->generateSlug();
            }
        });
    }

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

    public function getRouteKeyName()
    {
        return 'slug';
    }

    protected function generateDefaultAvatar(): string
    {
        $initial = $this->initial;
        $backgroundColor = $this->generateColorFromName();
        return "https://ui-avatars.com/api/?name={$initial}&background={$backgroundColor}&color=fff&size=128";
    }

    protected function generateColorFromName(): string
    {
        $colors = ['f44336', 'e91e63', '9c27b0', '673ab7', '3f51b5', '2196f3'];
        $hash = crc32($this->name ?: 'user');
        return $colors[$hash % count($colors)];
    }

    public function isOnline()
    {
        return Cache::has('user-is-online-' . $this->id);
    }
}
