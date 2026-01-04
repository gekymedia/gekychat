<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    /**
     * Get notification settings.
     * GET /notification-settings
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $userSettings = json_decode($user->settings ?? '{}', true);
        
        $settings = $userSettings['notifications'] ?? [
            'sound_enabled' => true,
            'desktop_enabled' => true,
            'preview_enabled' => true,
        ];

        return response()->json([
            'data' => $settings,
        ]);
    }

    /**
     * Update notification settings.
     * PUT /notification-settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'sound_enabled' => 'sometimes|boolean',
            'desktop_enabled' => 'sometimes|boolean',
            'preview_enabled' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $userSettings = json_decode($user->settings ?? '{}', true);
        
        if (!isset($userSettings['notifications'])) {
            $userSettings['notifications'] = [];
        }

        if ($request->has('sound_enabled')) {
            $userSettings['notifications']['sound_enabled'] = $request->boolean('sound_enabled');
        }
        if ($request->has('desktop_enabled')) {
            $userSettings['notifications']['desktop_enabled'] = $request->boolean('desktop_enabled');
        }
        if ($request->has('preview_enabled')) {
            $userSettings['notifications']['preview_enabled'] = $request->boolean('preview_enabled');
        }

        $user->settings = json_encode($userSettings);
        $user->save();

        return response()->json([
            'message' => 'Notification settings updated successfully',
            'data' => $userSettings['notifications'] ?? [],
        ]);
    }
}

