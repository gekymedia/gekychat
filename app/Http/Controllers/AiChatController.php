<?php

namespace App\Http\Controllers;

use App\Services\FeatureFlagService;
use Illuminate\Support\Facades\Auth;

class AiChatController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if (!FeatureFlagService::isEnabled('advanced_ai', $user, 'web')) {
            abort(403, 'AI Chat feature is not available');
        }
        
        return view('ai_chat.index');
    }
}

