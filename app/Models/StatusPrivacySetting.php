<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusPrivacySetting extends Model
{
    protected $fillable = [
        'user_id',
        'privacy',
        'excluded_user_ids',
        'included_user_ids',
    ];

    protected $casts = [
        'excluded_user_ids' => 'array',
        'included_user_ids' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a user can view the status based on privacy settings
     */
    public function canView(int $userId, int $statusOwnerId): bool
    {
        // User can always view their own status
        if ($userId === $statusOwnerId) {
            return true;
        }

        $privacy = $this->privacy;

        switch ($privacy) {
            case 'everyone':
                return true;

            case 'contacts':
                // Check if viewer is a contact of the status owner
                return Contact::where('user_id', $statusOwnerId)
                    ->where('contact_user_id', $userId)
                    ->exists();

            case 'contacts_except':
                // Check if viewer is a contact but not in excluded list
                $isContact = Contact::where('user_id', $statusOwnerId)
                    ->where('contact_user_id', $userId)
                    ->exists();
                
                $isExcluded = in_array($userId, $this->excluded_user_ids ?? []);
                
                return $isContact && !$isExcluded;

            case 'only_share_with':
                // Only users in the included list can view
                return in_array($userId, $this->included_user_ids ?? []);

            default:
                return false;
        }
    }
}

