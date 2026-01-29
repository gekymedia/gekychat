<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserPrivacySetting;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class PrivacySettingsController extends Controller
{
    /**
     * Get user's privacy settings
     */
    public function index(Request $request)
    {
        $settings = $request->user()->privacySettings;
        
        if (!$settings) {
            // Create default settings if don't exist
            $settings = UserPrivacySetting::create([
                'user_id' => $request->user()->id,
                'who_can_message' => 'everyone',
                'who_can_see_profile' => 'everyone',
                'who_can_see_last_seen' => 'everyone',
                'who_can_see_status' => 'contacts',
                'who_can_add_to_groups' => 'everyone',
                'who_can_call' => 'everyone',
                'profile_photo_visibility' => 'everyone',
                'about_visibility' => 'everyone',
                'send_read_receipts' => true,
                'send_typing_indicator' => true,
                'show_online_status' => true,
            ]);
        }
        
        return response()->json(['data' => $settings]);
    }
    
    /**
     * Update privacy settings
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'who_can_message' => 'sometimes|in:everyone,contacts,nobody',
            'who_can_see_profile' => 'sometimes|in:everyone,contacts,nobody',
            'who_can_see_last_seen' => 'sometimes|in:everyone,contacts,nobody',
            'who_can_see_status' => 'sometimes|in:everyone,contacts,contacts_except,only_share_with',
            'who_can_add_to_groups' => 'sometimes|in:everyone,contacts,admins_only',
            'who_can_call' => 'sometimes|in:everyone,contacts,nobody',
            'profile_photo_visibility' => 'sometimes|in:everyone,contacts,nobody',
            'about_visibility' => 'sometimes|in:everyone,contacts,nobody',
            'send_read_receipts' => 'sometimes|boolean',
            'send_typing_indicator' => 'sometimes|boolean',
            'show_online_status' => 'sometimes|boolean',
        ]);
        
        $oldSettings = $request->user()->privacySettings?->toArray();
        
        $settings = $request->user()->privacySettings()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );
        
        // Log the change
        AuditLog::log(
            'privacy_settings_updated',
            $settings,
            'User updated privacy settings',
            $oldSettings,
            $settings->toArray()
        );
        
        return response()->json([
            'data' => $settings,
            'message' => 'Privacy settings updated successfully'
        ]);
    }
}
