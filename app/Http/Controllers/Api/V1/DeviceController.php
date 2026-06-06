<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceController extends Controller 
{
    /**
     * Register FCM Token
     * POST /api/v1/notifications/register
     */
    public function register(Request $r) 
    {
        $r->validate([
            'token' => 'required|string',
            'device_type' => 'required|in:android,ios,web',
            'device_id' => 'required|string|max:255',
            'voip_token' => 'nullable|string|max:255',
        ]);

        $deviceToken = DeviceToken::register(
            $r->user()->id,
            $r->token,
            $r->device_type,
            $r->device_id,
            $r->input('voip_token'),
        );

        Log::info('Device token registered', [
            'user_id' => $r->user()->id,
            'device_type' => $r->device_type,
            'device_id' => $r->device_id,
            'token_id' => $deviceToken->id,
            'created' => $deviceToken->wasRecentlyCreated,
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Register iOS PushKit VoIP token (incoming calls when app is killed).
     * POST /api/v1/notifications/register-voip
     */
    public function registerVoip(Request $r)
    {
        $r->validate([
            'voip_token' => 'required|string|max:255',
            'device_id' => 'required|string|max:255',
        ]);

        if (! \Illuminate\Support\Facades\Schema::hasColumn('device_tokens', 'voip_token')) {
            return response()->json([
                'success' => false,
                'message' => 'VoIP tokens not supported on server yet',
            ], 501);
        }

        DeviceToken::registerVoipToken(
            $r->user()->id,
            $r->voip_token,
            $r->device_id,
        );

        Log::info('VoIP token registered', [
            'user_id' => $r->user()->id,
            'device_id' => $r->device_id,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Unregister device token
     * DELETE /api/v1/notifications/register
     */
    public function unregister(Request $r) 
    {
        $r->validate([
            'device_id' => 'required|string',
        ]);

        DeviceToken::where('user_id', $r->user()->id)
            ->where('device_id', $r->device_id)
            ->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
