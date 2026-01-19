<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use App\Services\PrivacyService;
use Illuminate\Http\Request;

class TypingController extends Controller
{
    /**
     * Broadcast typing start indicator
     * Checks privacy setting before broadcasting
     */
    public function start(Request $r, int $conversationId) {
        // Check privacy setting: if user has hide_typing enabled, don't broadcast
        if (!PrivacyService::shouldBroadcastTyping($r->user())) {
            // User has typing privacy enabled, return success but don't broadcast
            return ApiResponse::data(['ok'=>true, 'broadcasted'=>false]);
        }
        
        broadcast(new \App\Events\UserTyping($conversationId, null, $r->user()->id, true))->toOthers();
        return ApiResponse::data(['ok'=>true, 'broadcasted'=>true]);
    }

    /**
     * Broadcast typing stop indicator
     * Checks privacy setting before broadcasting
     */
    public function stop(Request $r, int $conversationId) {
        // Check privacy setting: if user has hide_typing enabled, don't broadcast
        if (!PrivacyService::shouldBroadcastTyping($r->user())) {
            // User has typing privacy enabled, return success but don't broadcast
            return ApiResponse::data(['ok'=>true, 'broadcasted'=>false]);
        }
        
        broadcast(new \App\Events\UserTyping($conversationId, null, $r->user()->id, false))->toOthers();
        return ApiResponse::data(['ok'=>true, 'broadcasted'=>true]);
    }
}
