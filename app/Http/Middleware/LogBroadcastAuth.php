<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogBroadcastAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('broadcasting/auth')) {
            Log::info('=== BROADCAST AUTH MIDDLEWARE ===', [
                'channel_name' => $request->input('channel_name'),
                'socket_id' => $request->input('socket_id'),
                'user_id' => auth()->id(),
            ]);
        }

        $response = $next($request);

        if ($request->is('broadcasting/auth')) {
            Log::info('=== BROADCAST AUTH RESPONSE ===', [
                'status' => $response->getStatusCode(),
                'channel_name' => $request->input('channel_name'),
            ]);
        }

        return $response;
    }
}
