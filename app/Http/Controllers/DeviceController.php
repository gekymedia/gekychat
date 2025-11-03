<?php

namespace App\Http\Controllers;

use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    public function index()
    {
        $sessions = UserSession::where('user_id', Auth::id())
            ->orderBy('last_activity', 'desc')
            ->get();

        $currentSessionId = session()->getId();

        return view('settings.devices', compact('sessions', 'currentSessionId'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'token' => 'required|string|max:255',
            'platform' => 'required|in:android,ios,web',
        ]);

        // Get device info from user agent
        $deviceInfo = $this->parseUserAgent($request->userAgent());

        DB::table('user_sessions')->updateOrInsert(
            [
                'user_id' => $request->user()->id,
                'session_id' => $request->token
            ],
            [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_type' => $deviceInfo['device_type'],
                'browser' => $deviceInfo['browser'],
                'platform' => $request->platform === 'web' ? $deviceInfo['platform'] : $request->platform,
                'location' => $this->getLocation($request->ip()),
                'is_current' => $request->token === session()->getId(),
                'last_activity' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        return response()->json(['success' => true]);
    }

    public function destroy($sessionId)
    {
        $session = UserSession::where('user_id', Auth::id())
            ->where('session_id', $sessionId)
            ->firstOrFail();

        // If this is the current session, we'll log them out
        if ($session->session_id === session()->getId()) {
            Auth::logout();
            $session->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Session terminated. You have been logged out.',
                'redirect' => route('login')
            ]);
        }

        $session->delete();

        // Also destroy the session from file/database if using database sessions
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('id', $sessionId)->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Device session terminated successfully.'
        ]);
    }

    public function destroyAllOther()
    {
        $currentSessionId = session()->getId();
        
        $sessions = UserSession::where('user_id', Auth::id())
            ->where('session_id', '!=', $currentSessionId)
            ->get();

        // Delete from user_sessions table
        UserSession::where('user_id', Auth::id())
            ->where('session_id', '!=', $currentSessionId)
            ->delete();

        // Delete from sessions table if using database sessions
        if (config('session.driver') === 'database') {
            DB::table('sessions')
                ->where('user_id', Auth::id())
                ->where('id', '!=', $currentSessionId)
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'All other sessions have been terminated.',
            'sessions_terminated' => $sessions->count()
        ]);
    }

    /**
     * Parse user agent string to get device information
     */
    private function parseUserAgent($userAgent)
    {
        $deviceType = 'desktop';
        $browser = 'Unknown';
        $platform = 'Unknown';

        // Simple device detection
        if (Str::contains($userAgent, ['Mobile', 'Android', 'iPhone', 'iPad'])) {
            $deviceType = Str::contains($userAgent, 'iPad') ? 'tablet' : 'mobile';
        }

        // Browser detection
        if (Str::contains($userAgent, 'Chrome')) {
            $browser = 'Chrome';
        } elseif (Str::contains($userAgent, 'Firefox')) {
            $browser = 'Firefox';
        } elseif (Str::contains($userAgent, 'Safari') && !Str::contains($userAgent, 'Chrome')) {
            $browser = 'Safari';
        } elseif (Str::contains($userAgent, 'Edge')) {
            $browser = 'Edge';
        }

        // Platform detection
        if (Str::contains($userAgent, 'Windows')) {
            $platform = 'Windows';
        } elseif (Str::contains($userAgent, 'Mac')) {
            $platform = 'macOS';
        } elseif (Str::contains($userAgent, 'Linux')) {
            $platform = 'Linux';
        } elseif (Str::contains($userAgent, 'Android')) {
            $platform = 'Android';
        } elseif (Str::contains($userAgent, 'iPhone') || Str::contains($userAgent, 'iPad')) {
            $platform = 'iOS';
        }

        return [
            'device_type' => $deviceType,
            'browser' => $browser,
            'platform' => $platform
        ];
    }

    /**
     * Get location from IP (simplified version)
     */
    private function getLocation($ip)
    {
        // In a real application, you might use a service like ipapi.co or ipinfo.io
        // For now, we'll return a simplified version
        if ($ip === '127.0.0.1' || Str::startsWith($ip, '192.168.') || Str::startsWith($ip, '10.')) {
            return 'Local Network';
        }

        // You can implement proper IP geolocation here
        return 'Unknown Location';
    }

    /**
     * Update session activity
     */
    public function updateActivity(Request $request)
    {
        $session = UserSession::where('user_id', Auth::id())
            ->where('session_id', session()->getId())
            ->first();

        if ($session) {
            $session->update([
                'last_activity' => now(),
                'ip_address' => $request->ip()
            ]);
        }

        return response()->json(['success' => true]);
    }
}