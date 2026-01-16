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
        // 🔍 TEMPORARY LOGGING: API Message Send Started
        \Log::info('API sendToPhone: Request received', [
            'phone' => $request->phone,
            'body_length' => strlen($request->body ?? ''),
            'bot_user_id' => $request->bot_user_id,
            'has_metadata' => !empty($request->metadata),
        ]);

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
        $apiCaller = $request->user(); // The user who owns the API client or the API key

        \Log::info('API sendToPhone: Auth context', [
            'has_client' => !is_null($client),
            'client_id' => $client?->id,
            'client_client_id' => $client?->client_id,
            'api_caller_user_id' => $apiCaller?->id,
            'api_caller_phone' => $apiCaller?->phone,
        ]);

        $normalizedPhone = Contact::normalizePhone($request->phone);
        
        \Log::info('API sendToPhone: Phone normalization', [
            'original_phone' => $request->phone,
            'normalized_phone' => $normalizedPhone,
        ]);

        // Find or create recipient user (users table only has 'phone' column, not 'normalized_phone')
        // Try exact match first, then fuzzy match with last 9 digits
        $recipientUser = User::where('phone', $normalizedPhone)
            ->orWhere('phone', ltrim($normalizedPhone, '+')) // Try without +
            ->orWhereRaw('RIGHT(REGEXP_REPLACE(phone, "[^0-9]", ""), 9) = ?', [Contact::last9($normalizedPhone)])
            ->first();

        \Log::info('API sendToPhone: Recipient user lookup', [
            'normalized_phone' => $normalizedPhone,
            'recipient_found' => !is_null($recipientUser),
            'recipient_user_id' => $recipientUser?->id,
            'recipient_phone' => $recipientUser?->phone,
        ]);

        $userAutoCreated = false;
        if (!$recipientUser) {
            if ($this->isAutoCreateEnabled($request)) {
                // Auto-create user for CUG and schoolsgh
                \Log::info('API sendToPhone: Auto-creating recipient user', [
                    'normalized_phone' => $normalizedPhone,
                ]);
                $recipientUser = User::create([
                    'phone' => $normalizedPhone,
                    'name' => $normalizedPhone, // Default name to phone number
                    'password' => Hash::make(Str::random(32)),
                    'phone_verified_at' => null,
                ]);
                $userAutoCreated = true;
                \Log::info('API sendToPhone: Recipient user created', [
                    'recipient_user_id' => $recipientUser->id,
                    'recipient_phone' => $recipientUser->phone,
                ]);
            } else {
                // Other platforms: return error
                \Log::warning('API sendToPhone: Recipient user not found and auto-create disabled', [
                    'normalized_phone' => $normalizedPhone,
                ]);
                return response()->json([
                    'error' => 'User not found. Phone number is not registered on GekyChat.',
                    'code' => 'USER_NOT_FOUND',
                ], 404);
            }
        }

        // Determine sender user: Use API caller (the authenticated user) as the sender
        // If no API caller (shouldn't happen with auth middleware), use bot as fallback
        if (!$apiCaller) {
            \Log::warning('API sendToPhone: No authenticated user found, using bot as fallback');
            $botUser = User::where('phone', '0000000000')->first();
            if (!$botUser) {
                \Log::error('API sendToPhone: Bot user not found');
                return response()->json([
                    'error' => 'System bot user not found',
                ], 404);
            }
            $senderUserId = $botUser->id;
        } else {
            $senderUserId = $apiCaller->id;
        }

        \Log::info('API sendToPhone: Sender and recipient', [
            'sender_user_id' => $senderUserId,
            'sender_phone' => User::find($senderUserId)?->phone,
            'recipient_user_id' => $recipientUser->id,
            'recipient_phone' => $recipientUser->phone,
            'using_bot_fallback' => !$apiCaller,
        ]);

        // Find or create conversation between API caller and recipient
        \Log::info('API sendToPhone: Finding/creating conversation', [
            'sender_user_id' => $senderUserId,
            'recipient_user_id' => $recipientUser->id,
        ]);
        
        $conversation = Conversation::findOrCreateDirect($senderUserId, $recipientUser->id, $senderUserId);
        
        \Log::info('API sendToPhone: Conversation result', [
            'conversation_id' => $conversation->id,
            'conversation_type' => $conversation->type ?? 'direct',
            'member_count' => $conversation->members()->count(),
            'members' => $conversation->members()->pluck('id', 'phone')->toArray(),
        ]);

        // Determine which API key was used (for user API keys via Sanctum)
        $userApiKeyId = null;
        if (!$client && $apiCaller) {
            // This is a user API key (Sanctum token), try to find which key was used
            // Check the token name which contains the API key ID
            $token = $request->user()->currentAccessToken();
            if ($token && str_starts_with($token->name, 'user-api-key-')) {
                $apiKeyId = str_replace('user-api-key-', '', $token->name);
                $userApiKey = \App\Models\UserApiKey::where('id', $apiKeyId)
                    ->where('user_id', $apiCaller->id)
                    ->where('is_active', true)
                    ->first();
                if ($userApiKey) {
                    $userApiKeyId = $userApiKey->id;
                    // Record usage
                    $userApiKey->recordUsage($request->ip());
                }
            }
        }

        // Create message
        \Log::info('API sendToPhone: Creating message', [
            'conversation_id' => $conversation->id,
            'sender_id' => $senderUserId,
            'sender_type' => 'platform',
            'platform_client_id' => $client?->id,
            'user_api_key_id' => $userApiKeyId,
            'body_length' => strlen($request->body),
            'has_metadata' => !empty($request->metadata),
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $senderUserId, // The API caller (or bot as fallback)
            'sender_type' => 'platform',
            'platform_client_id' => $client?->id, // May be null for user API keys
            'user_api_key_id' => $userApiKeyId, // Track which user API key was used
            'body' => $request->body,
            'metadata' => array_merge(
                $request->metadata ?? [],
                ['external_ref' => $request->external_ref]
            ),
        ]);

        \Log::info('API sendToPhone: Message created', [
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender_type' => $message->sender_type,
            'body_preview' => substr($message->body, 0, 50),
            'created_at' => $message->created_at,
        ]);

        // Verify message exists in database
        $verifyMessage = Message::find($message->id);
        \Log::info('API sendToPhone: Message verification', [
            'message_id' => $message->id,
            'message_exists_in_db' => !is_null($verifyMessage),
            'message_in_conversation' => $verifyMessage ? ($verifyMessage->conversation_id === $conversation->id) : false,
            'conversation_message_count' => $conversation->messages()->count(),
        ]);

        $message->load(['attachments']);

        // Notify users
        \Log::info('API sendToPhone: Broadcasting message', [
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
        ]);
        
        broadcast(new MessageSent($message));

        \Log::info('API sendToPhone: Success - Message sent', [
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'sender_user_id' => $senderUserId,
            'recipient_user_id' => $recipientUser->id,
            'user_auto_created' => $userAutoCreated,
        ]);

        return response()->json([
            'data' => [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'sender_user_id' => $senderUserId,
                'recipient_user_id' => $recipientUser->id,
                'user_auto_created' => $userAutoCreated,
                'status' => 'sent'
            ]
        ], 201);
    }
}
