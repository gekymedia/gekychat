<?php

namespace App\Http\Controllers;

use App\Services\FeatureFlagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorldFeedController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if (!FeatureFlagService::isEnabled('world_feed', $user, 'web')) {
            abort(403, 'World Feed feature is not available');
        }
        
        if (!$user->username) {
            return redirect()->route('settings.index')
                ->with('error', 'Username is required to access World Feed. Please set your username in Settings.');
        }
        
        return view('world_feed.index');
    }
}

