<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

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
        ]);

        $deviceToken = DeviceToken::register(
            $r->user()->id,
            $r->token,
            $r->device_type,
            $r->device_id
        );

        return response()->json([
            'success' => true,
        ]);
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
