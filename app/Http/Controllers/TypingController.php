<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\Request;

class TypingController extends Controller
{
    /**
     * PHASE 0: TODO (PHASE 1) - Check hide_typing privacy setting before broadcasting
     * Use PrivacyService::shouldBroadcastTyping() to check if user wants to hide typing indicator
     */
    public function start(Request $r, int $conversationId) {
        // TODO (PHASE 1): Check privacy setting: if (!PrivacyService::shouldBroadcastTyping($r->user())) { return; }
        broadcast(new \App\Events\UserTyping($conversationId, null, $r->user()->id, true))->toOthers();
        return ApiResponse::data(['ok'=>true]);
    }

    /**
     * PHASE 0: TODO (PHASE 1) - Check hide_typing privacy setting before broadcasting
     */
    public function stop(Request $r, int $conversationId) {
        // TODO (PHASE 1): Check privacy setting: if (!PrivacyService::shouldBroadcastTyping($r->user())) { return; }
        broadcast(new \App\Events\UserTyping($conversationId, null, $r->user()->id, false))->toOthers();
        return ApiResponse::data(['ok'=>true]);
    }
}
