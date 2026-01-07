<?php

namespace App\Http\Controllers;

use App\Services\FeatureFlagService;
use Illuminate\Support\Facades\Auth;

class LiveBroadcastController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if (!FeatureFlagService::isEnabled('live_broadcast', $user, 'web')) {
            abort(403, 'Live Broadcast feature is not available');
        }
        
        if (!$user->username) {
            return redirect()->route('settings.index')
                ->with('error', 'Username is required to start Live Broadcasts. Please set your username in Settings.');
        }
        
        return view('live_broadcast.index');
    }
}

