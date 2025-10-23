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
        'status'
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
        return Cache::has('user-is-online-' . $this->id) ||
            ($this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(2)));
    }

    public function markOnline(): void
    {
        Cache::put('user-is-online-' . $this->id, true, now()->addMinutes(2));
        $this->update(['last_seen_at' => now()]);
    }

    public function markOffline(): void
    {
        Cache::forget('user-is-online-' . $this->id);
    }

    public function updateLastSeen(): void
    {
        $this->markOnline();
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
