<?php

namespace App\Http\Controllers;

use App\Services\FeatureFlagService;
use Illuminate\Support\Facades\Auth;

class EmailChatController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if (!FeatureFlagService::isEnabled('email_chat', $user, 'web')) {
            abort(403, 'Email Chat feature is not available');
        }
        
        if (!$user->username) {
            return redirect()->route('settings.index')
                ->with('error', 'Username is required to access Email Chat. Please set your username in Settings.');
        }
        
        return view('email_chat.index');
    }
}

