<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
            'installation_id' => 'nullable|uuid',
            'device_id' => 'required_without:installation_id|string|max:255',
            'voip_token' => 'nullable|string|max:255',
        ]);

        $installationId = $r->input('installation_id');
        $deviceId = $r->input('device_id');

        $deviceToken = DeviceToken::register(
            $r->user()->id,
            $r->token,
            $r->device_type,
            $deviceId,
            $r->input('voip_token'),
            $installationId,
        );

        Log::info('Device token registered', [
            'user_id' => $r->user()->id,
            'device_type' => $r->device_type,
            'device_id' => $deviceId,
            'installation_id' => $installationId,
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
            'installation_id' => 'nullable|uuid',
            'device_id' => 'required_without:installation_id|string|max:255',
        ]);

        if (! Schema::hasColumn('device_tokens', 'voip_token')) {
            return response()->json([
                'success' => false,
                'message' => 'VoIP tokens not supported on server yet',
            ], 501);
        }

        DeviceToken::registerVoipToken(
            $r->user()->id,
            $r->voip_token,
            $r->input('device_id'),
            $r->input('installation_id'),
        );

        Log::info('VoIP token registered', [
            'user_id' => $r->user()->id,
            'device_id' => $r->input('device_id'),
            'installation_id' => $r->input('installation_id'),
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
            'installation_id' => 'nullable|uuid',
            'device_id' => 'required_without:installation_id|string',
        ]);

        $query = DeviceToken::where('user_id', $r->user()->id);

        if ($r->filled('installation_id') && Schema::hasColumn('device_tokens', 'installation_id')) {
            $query->where('installation_id', $r->installation_id);
        } else {
            $query->where('device_id', $r->device_id);
        }

        $query->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
