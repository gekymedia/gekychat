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
        // 🔍 TEMPORARY LOGGING: API Message Send Started
        \Log::info('API send: Request received', [
            'conversation_id' => $request->conversation_id,
            'body_length' => strlen($request->body ?? ''),
            'has_metadata' => !empty($request->metadata),
        ]);

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

        \Log::info('API send: Auth context', [
            'has_client' => !is_null($client),
            'client_id' => $client?->id,
            'client_client_id' => $client?->client_id,
            'auth_user_id' => $user?->id,
        ]);

        $conversation = Conversation::findOrFail($request->conversation_id);
        
        \Log::info('API send: Conversation found', [
            'conversation_id' => $conversation->id,
            'conversation_type' => $conversation->type ?? 'direct',
            'member_count' => $conversation->members()->count(),
            'members' => $conversation->members()->pluck('id', 'phone')->toArray(),
        ]);

        if (!$request->filled('body')) {
            return response()->json([
                'error' => 'Message body is required'
            ], 422);
        }

        \Log::info('API send: Creating message', [
            'conversation_id' => $conversation->id,
            'sender_id' => null,
            'sender_type' => 'platform',
            'platform_client_id' => $client?->id,
            'body_length' => strlen($request->body),
            'has_metadata' => !empty($request->metadata),
        ]);

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

        \Log::info('API send: Message created', [
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender_type' => $message->sender_type,
            'body_preview' => substr($message->body, 0, 50),
            'created_at' => $message->created_at,
        ]);

        // Verify message exists in database
        $verifyMessage = Message::find($message->id);
        \Log::info('API send: Message verification', [
            'message_id' => $message->id,
            'message_exists_in_db' => !is_null($verifyMessage),
            'message_in_conversation' => $verifyMessage ? ($verifyMessage->conversation_id === $conversation->id) : false,
            'conversation_message_count' => $conversation->messages()->count(),
        ]);

        $message->load(['attachments']);

        // 🔔 Notify USERS (NOT systems)
        \Log::info('API send: Broadcasting message', [
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
        ]);
        
        broadcast(new MessageSent($message));

        \Log::info('API send: Success - Message sent', [
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
        ]);

        return response()->json([
            'data' => [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'status' => 'sent'
            ]
        ], 201);
    }
}
