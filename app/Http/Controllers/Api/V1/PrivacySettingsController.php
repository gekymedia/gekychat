<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrivacySettingsController extends Controller
{
    /**
     * Get privacy settings for the authenticated user.
     * GET /privacy-settings
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get settings from user settings JSON column or return defaults
        $userSettings = json_decode($user->settings ?? '{}', true);
        $settings = $userSettings['privacy'] ?? [
            'last_seen' => 'everyone',
            'profile_photo' => 'everyone',
            'about' => 'everyone',
            'status' => [
                'who_can_see' => 'my_contacts',
                'exceptions' => [],
            ],
        ];

        return response()->json([
            'data' => $settings,
        ]);
    }

    /**
     * Update privacy settings.
     * PUT /privacy-settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'last_seen' => 'sometimes|in:everyone,my_contacts,nobody',
            'profile_photo' => 'sometimes|in:everyone,my_contacts,nobody',
            'about' => 'sometimes|in:everyone,my_contacts,nobody',
            'status' => 'sometimes|array',
            'status.who_can_see' => 'sometimes|in:my_contacts,my_contacts_except,only_share_with',
            'status.exceptions' => 'sometimes|array',
            'status.exceptions.*' => 'integer|exists:users,id',
        ]);

        $user = $request->user();
        $userSettings = json_decode($user->settings ?? '{}', true);
        
        if (!isset($userSettings['privacy'])) {
            $userSettings['privacy'] = [];
        }

        if ($request->has('last_seen')) {
            $userSettings['privacy']['last_seen'] = $request->input('last_seen');
        }
        if ($request->has('profile_photo')) {
            $userSettings['privacy']['profile_photo'] = $request->input('profile_photo');
        }
        if ($request->has('about')) {
            $userSettings['privacy']['about'] = $request->input('about');
        }
        if ($request->has('status')) {
            $userSettings['privacy']['status'] = $request->input('status');
        }

        $user->settings = json_encode($userSettings);
        $user->save();

        return response()->json([
            'message' => 'Privacy settings updated successfully',
            'data' => $userSettings['privacy'] ?? [],
        ]);
    }
}

