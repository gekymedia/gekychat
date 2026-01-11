<?php

namespace App\Http\Controllers;

use App\Services\FeatureFlagService;
use App\Http\Controllers\Traits\HasSidebarData;
use Illuminate\Support\Facades\Auth;

class LiveBroadcastController extends Controller
{
    use HasSidebarData;

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
        
        $sidebarData = $this->getSidebarData();
        
        return view('live_broadcast.index', $sidebarData);
    }

    public function watch($broadcastSlug)
    {
        $user = Auth::user();
        
        if (!FeatureFlagService::isEnabled('live_broadcast', $user, 'web')) {
            abort(403, 'Live Broadcast feature is not available');
        }
        
        $broadcast = \App\Models\LiveBroadcast::findByIdentifier($broadcastSlug);
        
        if (!$broadcast) {
            abort(404, 'Broadcast not found');
        }
        
        $isBroadcaster = $broadcast->broadcaster_id === $user->id;
        
        $sidebarData = $this->getSidebarData();
        $sidebarData['broadcastId'] = $broadcast->id; // Use ID for JavaScript
        $sidebarData['broadcastSlug'] = $broadcast->slug; // Use slug for URLs
        $sidebarData['isBroadcaster'] = $isBroadcaster;
        
        return view('live_broadcast.watch', $sidebarData);
    }
}

