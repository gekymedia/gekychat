<?php

namespace App\Http\Controllers\Api\Platform;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Alternative message sending endpoint that accepts phone number directly
 * and handles user/conversation creation automatically for CUG and schoolsgh
 */
class SendMessageController extends Controller
{
    /**
     * Check if auto-create is enabled for the current API client or user token
     * Checks for Special API Creation Privilege on:
     * - Platform API clients: the user who owns the API client
     * - User API keys (Sanctum): the tokenable user
     */
    protected function isAutoCreateEnabled(Request $request): bool
    {
        // Check for platform API client (auth:api-client)
        /** @var \App\Models\ApiClient|null $client */
        $client = $request->attributes->get('api_client');
        
        if ($client) {
            // Check if the user who owns this API client has Special API Creation Privilege
            $owner = $client->user;
            if ($owner && $owner->has_special_api_privilege) {
                return true;
            }

            // Legacy: Check for CUG and schoolsgh client_id patterns (backward compatibility)
            if ($client->client_id) {
                $clientId = $client->client_id;
                return str_starts_with($clientId, 'cug_platform_') || 
                       str_starts_with($clientId, 'schoolsgh_platform_');
            }
        }

        // Check for user API key (Sanctum token)
        $user = $request->user();
        if ($user && $user->has_special_api_privilege) {
            return true;
        }

        return false;
    }

    /**
     * Send a message to a phone number
     * For CUG and schoolsgh: auto-creates user and conversation if needed
     * For other platforms: returns error if user/conversation doesn't exist
     */
    public function sendToPhone(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'body' => 'required|string',
            'external_ref' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
            'bot_user_id' => 'nullable|integer',
        ]);

        // Get API client (platform) or user (Sanctum token)
        /** @var \App\Models\ApiClient|null $client */
        $client = $request->attributes->get('api_client');
        $user = $request->user(); // For Sanctum tokens

        $normalizedPhone = Contact::normalizePhone($request->phone);

        // Find or create user
        $user = User::where('normalized_phone', $normalizedPhone)
            ->orWhere('phone', $normalizedPhone)
            ->first();

        $userAutoCreated = false;
        if (!$user) {
            if ($this->isAutoCreateEnabled($request)) {
                // Auto-create user for CUG and schoolsgh
                $user = User::create([
                    'phone' => $normalizedPhone,
                    'normalized_phone' => $normalizedPhone,
                    'name' => $normalizedPhone, // Default name to phone number
                    'password' => Hash::make(Str::random(32)),
                    'phone_verified_at' => null,
                ]);
                $userAutoCreated = true;
            } else {
                // Other platforms: return error
                return response()->json([
                    'error' => 'User not found. Phone number is not registered on GekyChat.',
                    'code' => 'USER_NOT_FOUND',
                ], 404);
            }
        }

        // Get bot user
        $botUserId = $request->bot_user_id ?? 0;
        if ($botUserId === 0) {
            $botUser = User::where('phone', '0000000000')->first();
            if (!$botUser) {
                return response()->json([
                    'error' => 'System bot user not found',
                ], 404);
            }
            $botUserId = $botUser->id;
        }

        // Find or create conversation
        $conversation = Conversation::findOrCreateDirect($botUserId, $user->id, $botUserId);

        // Create message
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => null, // SYSTEM MESSAGE
            'sender_type' => 'platform',
            'platform_client_id' => $client?->id, // May be null for user API keys
            'body' => $request->body,
            'metadata' => array_merge(
                $request->metadata ?? [],
                ['external_ref' => $request->external_ref]
            ),
        ]);

        $message->load(['attachments']);

        // Notify users
        broadcast(new MessageSent($message));

        return response()->json([
            'data' => [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'user_auto_created' => $userAutoCreated,
                'status' => 'sent'
            ]
        ], 201);
    }
}
