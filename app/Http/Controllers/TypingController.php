<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\Request;

class TypingController extends Controller
{
    public function start(Request $r, int $conversationId) {
        broadcast(new \App\Events\TypingStarted($conversationId, $r->user()->id))->toOthers();
        return ApiResponse::data(['ok'=>true]);
    }

    public function stop(Request $r, int $conversationId) {
        broadcast(new \App\Events\TypingStopped($conversationId, $r->user()->id))->toOthers();
        return ApiResponse::data(['ok'=>true]);
    }
}
