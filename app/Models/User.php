<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'is_admin'              => 'boolean',
    ];

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

    /** DMs (each conversation has two FKs; we expose two relations) */
    public function conversationsAsUserOne(): HasMany
    {
        return $this->hasMany(Conversation::class, 'user_one_id');
    }

    public function conversationsAsUserTwo(): HasMany
    {
        return $this->hasMany(Conversation::class, 'user_two_id');
    }

    /**
     * Efficient base query for all conversations this user participates in.
     * (Use this instead of merging two collections.)
     */
    public function conversationsQuery()
    {
        return Conversation::query()->where(function ($q) {
            $q->where('user_one_id', $this->id)
              ->orWhere('user_two_id', $this->id);
        });
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
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
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

    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar_path
            ? asset('storage/'.$this->avatar_path)
            : asset('images/default-avatar.png');
    }

    /** Check participation by conversation id (no eager loading needed). */
    public function canJoinConversation(int $conversationId): bool
    {
        return Conversation::where('id', $conversationId)
            ->where(function ($q) {
                $q->where('user_one_id', $this->id)
                  ->orWhere('user_two_id', $this->id);
            })
            ->exists();
    }

    /** Check membership by group id efficiently. */
    public function belongsToGroup(int $groupId): bool
    {
        return $this->groups()->where('groups.id', $groupId)->exists();
    }

    /** Nicely named alias for event/broadcast auth checks. */
    public function isParticipantOfConversation(Conversation|int $conversation): bool
    {
        $id = is_object($conversation) ? $conversation->id : $conversation;

        return Conversation::where('id', $id)
            ->where(function ($q) {
                $q->where('user_one_id', $this->id)
                  ->orWhere('user_two_id', $this->id);
            })
            ->exists();
    }
}
