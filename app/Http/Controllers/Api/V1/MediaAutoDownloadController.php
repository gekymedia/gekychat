<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MediaAutoDownloadController extends Controller
{
    /**
     * Get media auto-download settings.
     * GET /media-auto-download
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $userSettings = json_decode($user->settings ?? '{}', true);
        
        $settings = $userSettings['media_auto_download'] ?? [
            'photos' => 'wifi_mobile',
            'videos' => 'wifi_only',
            'documents' => 'wifi_mobile',
            'audio' => 'wifi_mobile',
        ];

        return response()->json([
            'data' => $settings,
        ]);
    }

    /**
     * Update media auto-download settings.
     * PUT /media-auto-download
     */
    public function update(Request $request)
    {
        $request->validate([
            'photos' => 'sometimes|in:never,wifi_only,wifi_mobile,always',
            'videos' => 'sometimes|in:never,wifi_only,wifi_mobile,always',
            'documents' => 'sometimes|in:never,wifi_only,wifi_mobile,always',
            'audio' => 'sometimes|in:never,wifi_only,wifi_mobile,always',
        ]);

        $user = $request->user();
        $userSettings = json_decode($user->settings ?? '{}', true);
        
        if (!isset($userSettings['media_auto_download'])) {
            $userSettings['media_auto_download'] = [];
        }

        if ($request->has('photos')) {
            $userSettings['media_auto_download']['photos'] = $request->input('photos');
        }
        if ($request->has('videos')) {
            $userSettings['media_auto_download']['videos'] = $request->input('videos');
        }
        if ($request->has('documents')) {
            $userSettings['media_auto_download']['documents'] = $request->input('documents');
        }
        if ($request->has('audio')) {
            $userSettings['media_auto_download']['audio'] = $request->input('audio');
        }

        $user->settings = json_encode($userSettings);
        $user->save();

        return response()->json([
            'message' => 'Media auto-download settings updated successfully',
            'data' => $userSettings['media_auto_download'] ?? [],
        ]);
    }
}

