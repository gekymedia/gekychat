<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use App\Services\PrivacyService;
use Illuminate\Http\Request;

class RecordingController extends Controller
{
    /**
     * Broadcast recording start indicator
     * Checks privacy setting before broadcasting
     */
    public function start(Request $r, int $conversationId) {
        // Check privacy setting: if user has hide_typing enabled, don't broadcast
        if (!PrivacyService::shouldBroadcastTyping($r->user())) {
            // User has typing privacy enabled, return success but don't broadcast
            return ApiResponse::data(['ok'=>true, 'broadcasted'=>false]);
        }
        
        broadcast(new \App\Events\UserRecording($conversationId, null, $r->user()->id, true))->toOthers();
        return ApiResponse::data(['ok'=>true, 'broadcasted'=>true]);
    }

    /**
     * Broadcast recording stop indicator
     * Checks privacy setting before broadcasting
     */
    public function stop(Request $r, int $conversationId) {
        // Check privacy setting: if user has hide_typing enabled, don't broadcast
        if (!PrivacyService::shouldBroadcastTyping($r->user())) {
            // User has typing privacy enabled, return success but don't broadcast
            return ApiResponse::data(['ok'=>true, 'broadcasted'=>false]);
        }
        
        broadcast(new \App\Events\UserRecording($conversationId, null, $r->user()->id, false))->toOthers();
        return ApiResponse::data(['ok'=>true, 'broadcasted'=>true]);
    }
}
