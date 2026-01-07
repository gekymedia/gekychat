<?php

namespace App\Http\Controllers;

use App\Services\FeatureFlagService;
use App\Http\Controllers\Traits\HasSidebarData;
use Illuminate\Support\Facades\Auth;

class AiChatController extends Controller
{
    use HasSidebarData;

    public function index()
    {
        $user = Auth::user();
        
        if (!FeatureFlagService::isEnabled('advanced_ai', $user, 'web')) {
            abort(403, 'AI Chat feature is not available');
        }
        
        $sidebarData = $this->getSidebarData();
        
        return view('ai_chat.index', $sidebarData);
    }
}

