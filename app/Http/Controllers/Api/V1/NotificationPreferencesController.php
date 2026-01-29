<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;

class NotificationPreferencesController extends Controller
{
    /**
     * Get user's notification preferences
     */
    public function index(Request $request)
    {
        $preferences = $request->user()->notificationPreferences;
        
        if (!$preferences) {
            // Create default preferences if don't exist
            $preferences = NotificationPreference::create([
                'user_id' => $request->user()->id,
                'push_messages' => true,
                'push_group_messages' => true,
                'push_calls' => true,
                'push_status_updates' => true,
                'push_reactions' => true,
                'push_mentions' => true,
                'email_messages' => false,
                'email_weekly_digest' => true,
                'email_security_alerts' => true,
                'email_marketing' => false,
                'show_message_preview' => true,
                'notification_sound' => true,
                'vibrate' => true,
                'led_notification' => true,
                'quiet_hours_enabled' => false,
            ]);
        }
        
        return response()->json([
            'data' => $preferences,
            'is_quiet_hours' => $preferences->isQuietHours(),
        ]);
    }
    
    /**
     * Update notification preferences
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'push_messages' => 'sometimes|boolean',
            'push_group_messages' => 'sometimes|boolean',
            'push_calls' => 'sometimes|boolean',
            'push_status_updates' => 'sometimes|boolean',
            'push_reactions' => 'sometimes|boolean',
            'push_mentions' => 'sometimes|boolean',
            'email_messages' => 'sometimes|boolean',
            'email_weekly_digest' => 'sometimes|boolean',
            'email_security_alerts' => 'sometimes|boolean',
            'email_marketing' => 'sometimes|boolean',
            'show_message_preview' => 'sometimes|boolean',
            'notification_sound' => 'sometimes|boolean',
            'vibrate' => 'sometimes|boolean',
            'led_notification' => 'sometimes|boolean',
            'quiet_hours_start' => 'nullable|date_format:H:i:s',
            'quiet_hours_end' => 'nullable|date_format:H:i:s',
            'quiet_hours_enabled' => 'sometimes|boolean',
        ]);
        
        $preferences = $request->user()->notificationPreferences()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );
        
        return response()->json([
            'data' => $preferences,
            'message' => 'Notification preferences updated successfully',
            'is_quiet_hours' => $preferences->isQuietHours(),
        ]);
    }
}
