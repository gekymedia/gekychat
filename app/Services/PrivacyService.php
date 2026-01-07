<?php

namespace App\Services;

use App\Models\User;

/**
 * Privacy Service
 * 
 * PHASE 0: Foundation service for privacy enforcement.
 * This service will be used to check privacy settings before exposing user data.
 * 
 * TODO (PHASE 1): Implement all methods to enforce privacy settings server-side
 * TODO (PHASE 1): Wire this into controllers that expose user data (last seen, typing, etc.)
 */
class PrivacyService
{
    /**
     * Check if a user can see another user's last seen
     * 
     * @param User $viewer User trying to view
     * @param User $target User whose last seen is being viewed
     * @return bool
     */
    public static function canSeeLastSeen(User $viewer, User $target): bool
    {
        // TODO (PHASE 1): Check target's privacy settings['privacy']['last_seen']
        // - 'everyone' => true
        // - 'my_contacts' => check if viewer is in target's contacts
        // - 'nobody' => false
        return true; // Placeholder - currently allows all
    }
    
    /**
     * Check if a user should broadcast typing indicator
     * 
     * @param User $user User who is typing
     * @return bool
     */
    public static function shouldBroadcastTyping(User $user): bool
    {
        // TODO (PHASE 1): Check user's privacy settings['privacy']['hide_typing']
        // If true, return false (don't broadcast)
        return true; // Placeholder - currently broadcasts all
    }
    
    /**
     * Check if a user should show read receipts
     * 
     * @param User $user User sending read receipt
     * @return bool
     */
    public static function shouldSendReadReceipt(User $user): bool
    {
        // TODO (PHASE 1): Check user's privacy settings['privacy']['disable_read_receipts']
        // If true, return false (don't send)
        return true; // Placeholder - currently sends all
    }
    
    /**
     * Check if a user can see another user's profile photo
     * 
     * @param User $viewer User trying to view
     * @param User $target User whose photo is being viewed
     * @return bool
     */
    public static function canSeeProfilePhoto(User $viewer, User $target): bool
    {
        // TODO (PHASE 1): Check target's privacy settings['privacy']['profile_photo']
        // Similar logic to canSeeLastSeen
        return true; // Placeholder
    }
    
    /**
     * Check if a user can see another user's "about" text
     * 
     * @param User $viewer User trying to view
     * @param User $target User whose about is being viewed
     * @return bool
     */
    public static function canSeeAbout(User $viewer, User $target): bool
    {
        // TODO (PHASE 1): Check target's privacy settings['privacy']['about']
        // Similar logic to canSeeLastSeen
        return true; // Placeholder
    }
    
    /**
     * Check if a user can see another user's online status
     * 
     * @param User $viewer User trying to view
     * @param User $target User whose online status is being viewed
     * @return bool
     */
    public static function canSeeOnlineStatus(User $viewer, User $target): bool
    {
        // TODO (PHASE 1): Check target's privacy settings['privacy']['hide_online']
        // If true, return false (hide online status)
        return true; // Placeholder
    }
    
    /**
     * Check if a user should broadcast recording indicator
     * 
     * @param User $user User who is recording
     * @return bool
     */
    public static function shouldBroadcastRecording(User $user): bool
    {
        // TODO (PHASE 1): Check user's privacy settings['privacy']['hide_recording']
        // If true, return false (don't broadcast)
        return true; // Placeholder
    }

    /**
     * PHASE 1: Check if a user can view status stealthily (without being seen)
     * 
     * @param User $viewer User trying to view stealthily
     * @return bool
     */
    public static function canStealthViewStatus(User $viewer): bool
    {
        $settings = json_decode($viewer->settings ?? '{}', true);
        return $settings['privacy']['stealth_status_viewing'] ?? false;
    }

    /**
     * PHASE 1: Check if a user can view a status based on privacy settings
     * 
     * @param User $viewer User trying to view
     * @param int $statusOwnerId ID of status owner
     * @return bool
     */
    public static function canViewStatus(User $viewer, int $statusOwnerId): bool
    {
        // Status model already has canBeViewedBy method, but we can use this as a service wrapper
        // For now, return true and let Status model handle the check
        return true; // Status->canBeViewedBy() is used directly in controllers
    }
}

