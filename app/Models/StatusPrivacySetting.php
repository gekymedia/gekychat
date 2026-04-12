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
        return self::viewerMaySeeStatus(
            $userId,
            $statusOwnerId,
            (string) ($this->privacy ?? 'contacts'),
            $this->excluded_user_ids,
            $this->included_user_ids
        );
    }

    /**
     * Shared rules for global settings and per-status overrides.
     */
    public static function viewerMaySeeStatus(
        int $viewerId,
        int $statusOwnerId,
        string $privacy,
        ?array $excludedUserIds,
        ?array $includedUserIds
    ): bool {
        if ($viewerId === $statusOwnerId) {
            return true;
        }

        $excluded = $excludedUserIds ?? [];
        $included = $includedUserIds ?? [];

        switch ($privacy) {
            case 'everyone':
                return true;

            case 'contacts':
                return Contact::where('user_id', $statusOwnerId)
                    ->where('contact_user_id', $viewerId)
                    ->exists();

            case 'contacts_except':
                $isContact = Contact::where('user_id', $statusOwnerId)
                    ->where('contact_user_id', $viewerId)
                    ->exists();

                return $isContact && ! in_array($viewerId, $excluded, true);

            case 'only_share_with':
                return in_array($viewerId, $included, true);

            default:
                return false;
        }
    }
}

