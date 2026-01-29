<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPrivacySetting extends Model
{
    protected $fillable = [
        'user_id',
        'who_can_message',
        'who_can_see_profile',
        'who_can_see_last_seen',
        'who_can_see_status',
        'who_can_add_to_groups',
        'who_can_call',
        'profile_photo_visibility',
        'about_visibility',
        'send_read_receipts',
        'send_typing_indicator',
        'show_online_status',
    ];

    protected $casts = [
        'send_read_receipts' => 'boolean',
        'send_typing_indicator' => 'boolean',
        'show_online_status' => 'boolean',
    ];

    /**
     * Get the user that owns the privacy settings
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user can perform action based on privacy settings
     */
    public function canMessage(User $requester): bool
    {
        return match ($this->who_can_message) {
            'everyone' => true,
            'contacts' => $this->user->contacts()->where('contact_user_id', $requester->id)->exists(),
            'nobody' => false,
            default => true,
        };
    }

    /**
     * Check if user profile is visible to requester
     */
    public function canSeeProfile(User $requester): bool
    {
        return match ($this->who_can_see_profile) {
            'everyone' => true,
            'contacts' => $this->user->contacts()->where('contact_user_id', $requester->id)->exists(),
            'nobody' => false,
            default => true,
        };
    }

    /**
     * Check if last seen is visible to requester
     */
    public function canSeeLastSeen(User $requester): bool
    {
        return match ($this->who_can_see_last_seen) {
            'everyone' => true,
            'contacts' => $this->user->contacts()->where('contact_user_id', $requester->id)->exists(),
            'nobody' => false,
            default => true,
        };
    }
}
