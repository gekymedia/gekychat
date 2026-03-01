<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BotContact;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BotController extends Controller
{
    /**
     * List all available bots for discovery
     * 
     * GET /api/v1/bots
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Get all active bots
        $bots = BotContact::where('is_active', true)
            ->orderBy('bot_number', 'asc')
            ->get();
        
        // Get user's existing bot contacts
        $existingBotUserIds = $user->contacts()
            ->whereHas('contactUser', function ($q) use ($bots) {
                $q->whereIn('phone', $bots->pluck('bot_number'));
            })
            ->pluck('contact_user_id')
            ->toArray();
        
        $botsData = $bots->map(function ($bot) use ($existingBotUserIds) {
            $botUser = $bot->user();
            $isAdded = $botUser ? in_array($botUser->id, $existingBotUserIds) : false;
            
            return [
                'bot_number' => $bot->bot_number,
                'name' => $bot->name,
                'type' => $bot->bot_type,
                'description' => $bot->description,
                'avatar_url' => $botUser?->avatar_url,
                'is_added' => $isAdded,
                'auto_added' => $bot->auto_add_to_contacts,
            ];
        });
        
        return response()->json([
            'success' => true,
            'bots' => $botsData,
        ]);
    }

    /**
     * Get details of a specific bot
     * 
     * GET /api/v1/bots/{botNumber}
     */
    public function show(Request $request, string $botNumber): JsonResponse
    {
        $user = $request->user();
        
        $bot = BotContact::where('bot_number', $botNumber)
            ->where('is_active', true)
            ->first();
        
        if (!$bot) {
            return response()->json([
                'success' => false,
                'message' => 'Bot not found',
            ], 404);
        }
        
        $botUser = $bot->user();
        
        // Check if user already has this bot
        $isAdded = false;
        if ($botUser) {
            $isAdded = $user->contacts()
                ->where('contact_user_id', $botUser->id)
                ->exists();
        }
        
        return response()->json([
            'success' => true,
            'bot' => [
                'bot_number' => $bot->bot_number,
                'name' => $bot->name,
                'type' => $bot->bot_type,
                'description' => $bot->description,
                'avatar_url' => $botUser?->avatar_url,
                'is_added' => $isAdded,
                'auto_added' => $bot->auto_add_to_contacts,
                'user_id' => $botUser?->id,
            ],
        ]);
    }

    /**
     * Add a bot to user's contacts and create conversation
     * 
     * POST /api/v1/bots/{botNumber}/add
     */
    public function addToContacts(Request $request, string $botNumber): JsonResponse
    {
        $user = $request->user();
        
        $bot = BotContact::where('bot_number', $botNumber)
            ->where('is_active', true)
            ->first();
        
        if (!$bot) {
            return response()->json([
                'success' => false,
                'message' => 'Bot not found',
            ], 404);
        }
        
        // Get or create the bot user
        $botUser = $bot->getOrCreateUser();
        
        // Check if already added
        $existingContact = $user->contacts()
            ->where('contact_user_id', $botUser->id)
            ->first();
        
        if ($existingContact) {
            // Already exists, just return the conversation
            $conversation = Conversation::findOrCreateDirect($user->id, $botUser->id);
            
            return response()->json([
                'success' => true,
                'message' => 'Bot already in your contacts',
                'contact' => [
                    'id' => $existingContact->id,
                    'user_id' => $botUser->id,
                    'name' => $botUser->name,
                    'phone' => $botUser->phone,
                    'avatar_url' => $botUser->avatar_url,
                ],
                'conversation_id' => $conversation->id,
            ]);
        }
        
        // Add to contacts
        $contact = $user->contacts()->create([
            'contact_user_id' => $botUser->id,
            'display_name' => $botUser->name,
            'is_favorite' => false,
            'phone' => $botUser->phone,
            'normalized_phone' => Contact::normalizePhone($botUser->phone ?? ''),
            'source' => 'bot_discovery',
        ]);
        
        // Create conversation
        $conversation = Conversation::findOrCreateDirect($user->id, $botUser->id);
        
        return response()->json([
            'success' => true,
            'message' => "Added {$bot->name} to your contacts",
            'contact' => [
                'id' => $contact->id,
                'user_id' => $botUser->id,
                'name' => $botUser->name,
                'phone' => $botUser->phone,
                'avatar_url' => $botUser->avatar_url,
            ],
            'conversation_id' => $conversation->id,
        ]);
    }
}
