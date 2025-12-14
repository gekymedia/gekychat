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
    public function send(Request $request, int $conversationId)
    {
        $request->validate([
            'body'              => 'nullable|string',
            'external_ref'      => 'nullable|string|max:255',
            'metadata'          => 'nullable|array',
        ]);

        /** @var \App\Models\ApiClient $client */
        $client = $request->attributes->get('api_client');

        $conversation = Conversation::findOrFail($conversationId);

        if (!$request->filled('body')) {
            return response()->json([
                'error' => 'Message body is required'
            ], 422);
        }

        $message = Message::create([
            'conversation_id'   => $conversation->id,
            'sender_id'         => null, // SYSTEM MESSAGE
            'sender_type'       => 'platform',
            'platform_client_id'=> $client->id,
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
