<?php

namespace App\Http\Controllers\Api\Platform;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * Send a message INTO a conversation from a platform system
     * (e.g CRM, Bot, ERP, Website widget)
     */
    public function send(Request $request)
    {
        $request->validate([
            'conversation_id'   => 'required|integer|exists:conversations,id',
            'body'              => 'required|string',
            'external_ref'      => 'nullable|string|max:255',
            'metadata'          => 'nullable|array',
        ]);

        // Get API client (platform) or user (Sanctum token)
        /** @var \App\Models\ApiClient|null $client */
        $client = $request->attributes->get('api_client');
        $user = $request->user(); // For Sanctum tokens

        $conversation = Conversation::findOrFail($request->conversation_id);

        if (!$request->filled('body')) {
            return response()->json([
                'error' => 'Message body is required'
            ], 422);
        }

        $message = Message::create([
            'conversation_id'   => $conversation->id,
            'sender_id'         => null, // SYSTEM MESSAGE
            'sender_type'       => 'platform',
            'platform_client_id'=> $client?->id, // May be null for user API keys
            'body'              => $request->body,
            'metadata'          => array_merge(
                $request->metadata ?? [],
                ['external_ref' => $request->external_ref]
            ),
        ]);

        $message->load(['attachments']);

        // 🔔 Notify USERS (NOT systems)
        broadcast(new MessageSent($message));

        return response()->json([
            'data' => [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'status' => 'sent'
            ]
        ], 201);
    }
}
