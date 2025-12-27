<?php

namespace App\Http\Controllers;

use App\Services\ContactSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class SearchController extends Controller
{
    protected $searchService;

    public function __construct(ContactSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * GET /api/v1/search?q=&limit=&filters[]=
     * Returns unified results with all new features:
     * - Message content search
     * - Recent chats when empty
     * - Search filters
     * - Phone number detection
     * - Contact and user search
     * - Self-chat/Saved Messages support
     */
    public function index(Request $request)
    {
        // Support both web and API authentication
        $user = $request->user();
        if (!$user) {
            $user = Auth::user();
        }
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $userId = $user->id;
        $query = trim((string) $request->query('q', ''));
        $filters = (array) $request->query('filters', []);
        $limit = (int) $request->query('limit', 20);
        $limit = max(1, min($limit, 100));

        // Use the enhanced search service
        $results = $this->searchService->search($userId, $query, $filters, $limit);

        return response()->json([
            'success' => true,
            'query' => $query,
            'filters' => $filters,
            'results' => $results,
            'has_more' => count($results) >= $limit,
        ]);
    }

    /**
     * GET /api/v1/search/filters
     * Returns available search filters
     */
    public function searchFilters()
    {
        return response()->json([
            'available_filters' => [
                ['key' => 'contacts', 'label' => 'Contacts', 'icon' => 'user'],
                ['key' => 'users', 'label' => 'People', 'icon' => 'users'],
                ['key' => 'groups', 'label' => 'Groups', 'icon' => 'group'],
                ['key' => 'messages', 'label' => 'Messages', 'icon' => 'message'],
                ['key' => 'unread', 'label' => 'Unread', 'icon' => 'mail-unread'],
                ['key' => 'saved', 'label' => 'Saved Messages', 'icon' => 'bookmark'],
            ]
        ]);
    }

    /**
     * POST /api/v1/start-chat-with-phone
     * Start chat with phone number (like WhatsApp)
     */
    public function startChatWithPhone(Request $request)
    {
        $request->validate([
            'phone' => ['required','string','min:10'],
        ]);

        $currentUser = Auth::user();
        $rawPhone    = (string) $request->phone;

        // Normalize phone number using your Contact helper
        $normalizedPhone = \App\Models\Contact::normalizePhone($rawPhone);

        // Generate all possible phone formats to search for
        $phoneFormats = $this->getAllPhoneFormats($rawPhone, $normalizedPhone);

        // Look up an existing account by phone (try all formats)
        /** @var \App\Models\User|null $targetUser */
        $targetUser = \App\Models\User::whereIn('phone', $phoneFormats)->first();

        // If user exists in database, they are registered
        // Only exclude users who are explicitly banned or deleted
        $isRegistered = false;
        if ($targetUser) {
            // User is registered if they exist and are not banned
            $isRegistered = $targetUser->status !== 'banned' 
                && ($targetUser->banned_until === null || $targetUser->banned_until->isPast());
        }

        // If NOT registered → do NOT create a user. Return invite details.
        if (!$isRegistered) {
            // Build a lightweight invite deep link (pre-fills phone on your register page)
            // If you have a dedicated onboarding route, point to that instead.
            $registerUrl = route('login', ['phone' => ltrim($normalizedPhone, '+')]);

            $appName  = config('app.name', 'GekyChat');
            $smsText  = "Hi! Join me on {$appName}. Tap to register: {$registerUrl}";
            $shareTxt = "Let's chat on {$appName}! Create your account here: {$registerUrl}";

            return response()->json([
                'success'         => true,
                'not_registered'  => true,
                'phone'           => $normalizedPhone,
                'invite'          => [
                    'register_url' => $registerUrl,
                    'sms_text'     => $smsText,
                    'share_text'   => $shareTxt,
                ],
            ]);
        }

        // If registered → create (or find) the DM conversation and go
        $conversation = \App\Models\Conversation::findOrCreateDirect($currentUser->id, $targetUser->id);

        return response()->json([
            'success'      => true,
            'not_registered' => false,
            'conversation' => $conversation->load(['members', 'latestMessage']),
            'redirect_url' => route('chat.show', $conversation->slug),
        ]);
    }

    /**
     * Legacy search endpoint for backward compatibility
     * GET /api/v1/search/legacy?q=&limit=
     * Returns unified results for conversations and groups only.
     * Shape: [{id, type, name, avatar_url, last_message, updated_at}]
     * NOW INCLUDES SELF-CHATS/SAVED MESSAGES
     */
    public function legacySearch(Request $request)
    {
        $userId = $request->user()->id;
        $q      = trim((string) $request->query('q', ''));
        $limit  = (int) $request->query('limit', 50);
        $limit  = max(1, min($limit, 100));

        // --- DMs (conversations the current user belongs to) INCLUDING SELF-CHATS ---
        $dmResults = $this->searchDmConversations($userId, $q, $limit);

        // --- Groups (only those the user belongs to) ---
        $groupResults = $this->searchGroups($userId, $q, $limit);

        // Merge, sort by updated_at desc, then slice to total limit
        $all = array_merge($dmResults, $groupResults);
        usort($all, function ($a, $b) {
            $ta = Carbon::parse($a['updated_at'] ?? '1970-01-01T00:00:00Z');
            $tb = Carbon::parse($b['updated_at'] ?? '1970-01-01T00:00:00Z');
            return $tb <=> $ta;
        });
        $all = array_slice($all, 0, $limit);

        return response()->json([
            'success' => true,
            'results' => $all
        ]);
    }

    /**
     * Build DM search: conversations joined with "other user" + last message.
     * NOW INCLUDES SELF-CHATS/SAVED MESSAGES
     */
    protected function searchDmConversations(int $userId, string $q, int $limit): array
    {
        if (!Schema::hasTable('conversations') || !Schema::hasTable('conversation_user')) {
            return [];
        }

        // Subquery: last message per conversation
        $lastDmSub = DB::table('messages')
            ->selectRaw('conversation_id, MAX(id) as last_id, MAX(created_at) as last_at')
            ->groupBy('conversation_id');

        // Subquery: count members per conversation
        $memberCountSub = DB::table('conversation_user')
            ->selectRaw('conversation_id, COUNT(*) as member_count')
            ->groupBy('conversation_id');

        $builder = DB::table('conversations as c')
            // current user's membership
            ->join('conversation_user as cu', function ($j) use ($userId) {
                $j->on('cu.conversation_id', '=', 'c.id')
                  ->where('cu.user_id', '=', $userId);
            })
            // member count
            ->leftJoinSub($memberCountSub, 'mc', 'mc.conversation_id', '=', 'c.id')
            // the "other" participant (for 1:1 DMs) - exclude for self-chats
            ->leftJoin('conversation_user as cu2', function ($j) use ($userId) {
                $j->on('cu2.conversation_id', '=', 'c.id')
                  ->where('cu2.user_id', '<>', $userId);
            })
            ->leftJoin('users as u', 'u.id', '=', 'cu2.user_id')
            // last message
            ->leftJoinSub($lastDmSub, 'lm', function ($j) {
                $j->on('lm.conversation_id', '=', 'c.id');
            })
            ->leftJoin('messages as m', 'm.id', '=', 'lm.last_id')
            ->selectRaw("
                c.id as conversation_id,
                CASE 
                    WHEN mc.member_count = 1 THEN 'Saved Messages'
                    ELSE COALESCE(NULLIF(TRIM(u.name), ''), u.phone, CONCAT('Chat #', c.id)) 
                END as partner_name,
                CASE 
                    WHEN mc.member_count = 1 THEN 'bi-bookmark'
                    ELSE u.avatar_path 
                END as partner_avatar,
                m.body as last_body,
                COALESCE(m.created_at, c.updated_at, c.created_at) as last_at,
                c.slug as conversation_slug,
                mc.member_count
            ")
            // basic LIKE search on partner name/phone and last message
            ->when($q !== '', function ($w) use ($q) {
                $like = '%' . str_replace(['%','_'], ['\\%','\\_'], $q) . '%';
                $w->where(function ($x) use ($like) {
                    $x->where('u.name', 'like', $like)
                      ->orWhere('u.phone', 'like', $like)
                      ->orWhere('m.body', 'like', $like)
                      // Also search in self-chats when query matches
                      ->orWhereRaw("mc.member_count = 1 AND m.body LIKE ?", [$like]);
                });
            })
            ->orderByRaw('COALESCE(m.created_at, c.updated_at, c.created_at) DESC')
            ->limit($limit);

        $rows = $builder->get();

        $out = [];
        foreach ($rows as $r) {
            $isSelfChat = $r->member_count == 1;
            
            $out[] = [
                'id'           => (int) $r->conversation_id,
                'type'         => 'conversation',
                'name'         => (string) $r->partner_name,
                'avatar_url'   => $isSelfChat ? asset('icons/bookmark-icon.png') : 
                                 ($r->partner_avatar && $r->partner_avatar !== 'bi-bookmark' ? asset('storage/'.$r->partner_avatar) : asset('images/default-avatar.png')),
                'avatar_icon'  => $isSelfChat ? 'bi-bookmark' : null,
                'last_message' => $r->last_body ? (string) $r->last_body : null,
                'updated_at'   => Carbon::parse($r->last_at ?? now())->toISOString(),
                'slug'         => $r->conversation_slug,
                'unread_count' => $this->getConversationUnreadCount($r->conversation_id, $userId),
                'is_self_chat' => $isSelfChat,
            ];
        }
        return $out;
    }

    /**
     * Build Group search: groups the user is in + last message.
     * Supports either "group_user" or "group_members" pivot table.
     */
    protected function searchGroups(int $userId, string $q, int $limit): array
    {
        if (!Schema::hasTable('groups')) {
            return [];
        }

        // Determine pivot name
        $pivot = Schema::hasTable('group_user') ? 'group_user' :
                 (Schema::hasTable('group_members') ? 'group_members' : null);

        if (!$pivot) {
            // No membership table → avoid leaking groups
            return [];
        }

        // Subquery: last message per group
        $lastGroupSub = DB::table('group_messages')
            ->selectRaw('group_id, MAX(id) as last_id, MAX(created_at) as last_at')
            ->groupBy('group_id');

        $builder = DB::table('groups as g')
            ->join($pivot . ' as gu', function ($j) use ($userId, $pivot) {
                // support either column naming
                $j->on('gu.group_id', '=', 'g.id');
                $j->where('gu.user_id', '=', $userId);
            })
            ->leftJoinSub($lastGroupSub, 'lm', function ($j) {
                $j->on('lm.group_id', '=', 'g.id');
            })
            ->leftJoin('group_messages as gm', 'gm.id', '=', 'lm.last_id')
            ->selectRaw("
                g.id as group_id,
                COALESCE(NULLIF(TRIM(g.name), ''), CONCAT('Group #', g.id)) as group_name,
                g.avatar_path as group_avatar,
                gm.body as last_body,
                COALESCE(gm.created_at, g.updated_at, g.created_at) as last_at,
                g.slug as group_slug
            ")
            ->when($q !== '', function ($w) use ($q) {
                $like = '%' . str_replace(['%','_'], ['\\%','\\_'], $q) . '%';
                $w->where(function ($x) use ($like) {
                    $x->where('g.name', 'like', $like)
                      ->orWhere('gm.body', 'like', $like);
                });
            })
            ->orderByRaw('COALESCE(gm.created_at, g.updated_at, g.created_at) DESC')
            ->limit($limit);

        $rows = Schema::hasTable('group_messages') ? $builder->get() : collect();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'           => (int) $r->group_id,
                'type'         => 'group',
                'name'         => (string) $r->group_name,
                'avatar_url'   => $r->group_avatar ? \App\Helpers\UrlHelper::secureStorageUrl($r->group_avatar) : \App\Helpers\UrlHelper::secureAsset('images/group-default.png'),
                'last_message' => $r->last_body ? (string) $r->last_body : null,
                'updated_at'   => Carbon::parse($r->last_at ?? now())->toISOString(),
                'slug'         => $r->group_slug,
                'unread_count' => $this->getGroupUnreadCount($r->group_id, $userId),
                'is_self_chat' => false,
            ];
        }
        return $out;
    }

    /**
     * Get unread count for conversation
     */
    protected function getConversationUnreadCount(int $conversationId, int $userId): int
    {
        try {
            $conversation = \App\Models\Conversation::find($conversationId);
            return $conversation ? $conversation->unreadCountFor($userId) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get unread count for group
     */
    protected function getGroupUnreadCount(int $groupId, int $userId): int
    {
        try {
            $group = \App\Models\Group::find($groupId);
            return $group ? $group->unreadCountFor($userId) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * GET /api/v1/search/contacts?q=&limit=
     * Search only contacts (for filter usage)
     */
    public function searchContacts(Request $request)
    {
        $userId = $request->user()->id;
        $query = trim((string) $request->query('q', ''));
        $limit = (int) $request->query('limit', 20);

        $contacts = \App\Models\Contact::where('user_id', $userId)
            ->when($query !== '', function ($q) use ($query) {
                $searchTerm = '%' . str_replace(['%','_'], ['\\%','\\_'], $query) . '%';
                $phoneDigits = preg_replace('/\D+/', '', $query);
                
                $q->where(function ($builder) use ($searchTerm, $phoneDigits) {
                    $builder->where('display_name', 'like', $searchTerm)
                           ->orWhere('phone', 'like', $searchTerm)
                           ->orWhere('normalized_phone', 'like', '%' . $phoneDigits . '%');
                });
            })
            ->with('contactUser')
            ->limit($limit)
            ->get()
            ->map(function ($contact) {
                return [
                    'type' => 'contact',
                    'id' => $contact->id,
                    'display_name' => $contact->display_name,
                    'phone' => $contact->phone,
                    'user' => $contact->contactUser,
                    'avatar_url' => $contact->contactUser->avatar_url ?? null,
                    'is_registered' => !is_null($contact->contact_user_id),
                ];
            });

        return response()->json([
            'success' => true,
            'results' => $contacts
        ]);
    }

    /**
     * GET /api/v1/search/messages?q=&limit=
     * Search only messages (for filter usage)
     * NOW INCLUDES MESSAGES FROM SELF-CHATS
     */
    public function searchMessages(Request $request)
    {
        $userId = $request->user()->id;
        $query = trim((string) $request->query('q', ''));
        $limit = (int) $request->query('limit', 20);

        // Search in both regular conversations and self-chats
        $messages = \App\Models\Message::where('body', 'like', '%' . $query . '%')
            ->where(function ($q) use ($userId) {
                $q->whereHas('conversation.members', function ($memberQuery) use ($userId) {
                    $memberQuery->where('user_id', $userId);
                });
            })
            ->with(['conversation' => function ($query) {
                $query->withCount('members');
            }, 'sender'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($message) use ($query) {
                $isSelfChat = $message->conversation->members_count == 1;
                
                return [
                    'type' => 'message',
                    'id' => $message->id,
                    'body' => $message->body,
                    'snippet' => $this->createMessageSnippet($message->body, $query),
                    'conversation' => $message->conversation,
                    'sender' => $message->sender,
                    'created_at' => $message->created_at->toISOString(),
                    'conversation_slug' => $message->conversation->slug,
                    'is_self_chat_message' => $isSelfChat,
                    'conversation_name' => $isSelfChat ? 'Saved Messages' : $message->conversation->title,
                ];
            });

        return response()->json([
            'success' => true,
            'results' => $messages
        ]);
    }

    /**
     * Create message snippet with highlighted context
     */
    protected function createMessageSnippet(string $body, string $query): string
    {
        $query = strtolower($query);
        $body = strip_tags($body);
        
        // Find position of query in message
        $pos = stripos($body, $query);
        
        if ($pos === false) {
            return Str::limit($body, 50);
        }
        
        // Create snippet with context
        $start = max(0, $pos - 20);
        $snippet = substr($body, $start, 60);
        
        if ($start > 0) {
            $snippet = '...' . $snippet;
        }
        
        if ($start + 60 < strlen($body)) {
            $snippet = $snippet . '...';
        }
        
        return $snippet;
    }

    /**
     * GET /api/v1/search/saved-messages?q=&limit=
     * Search specifically in saved messages/self-chats
     */
    public function searchSavedMessages(Request $request)
    {
        $userId = $request->user()->id;
        $query = trim((string) $request->query('q', ''));
        $limit = (int) $request->query('limit', 20);

        // Find self-chat conversations (where user is the only member)
        $selfChats = \App\Models\Conversation::whereHas('members', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->withCount('members')
        ->having('members_count', '=', 1)
        ->get()
        ->pluck('id');

        if ($selfChats->isEmpty()) {
            return response()->json([
                'success' => true,
                'results' => []
            ]);
        }

        $messages = \App\Models\Message::whereIn('conversation_id', $selfChats)
            ->when($query !== '', function ($q) use ($query) {
                $q->where('body', 'like', '%' . $query . '%');
            })
            ->with(['sender'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($message) use ($query) {
                return [
                    'type' => 'saved_message',
                    'id' => $message->id,
                    'body' => $message->body,
                    'snippet' => $this->createMessageSnippet($message->body, $query),
                    'sender' => $message->sender,
                    'created_at' => $message->created_at->toISOString(),
                    'is_encrypted' => $message->is_encrypted,
                ];
            });

        return response()->json([
            'success' => true,
            'results' => $messages
        ]);
    }

    /**
     * POST /api/v1/conversations/create-self-chat
     * Create a self-chat/saved messages conversation for the current user
     */
    public function createSelfChat(Request $request)
    {
        $userId = $request->user()->id;

        try {
            $selfChat = $this->findOrCreateSelfChat($userId);

            return response()->json([
                'success' => true,
                'conversation' => $selfChat->load(['members', 'latestMessage']),
                'redirect_url' => route('chat.show', $selfChat->slug),
                'message' => 'Saved Messages chat created successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create saved messages chat: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Find or create self-chat for user
     */
    protected function findOrCreateSelfChat(int $userId)
    {
        // Look for existing self-chat (conversation with only this user)
        $selfChat = \App\Models\Conversation::whereHas('members', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->withCount('members')
        ->having('members_count', '=', 1)
        ->first();

        if (!$selfChat) {
            // Create new self-chat
            $selfChat = \App\Models\Conversation::create([
                'title' => 'Saved Messages',
                'slug' => 'saved-messages-' . Str::random(8),
                'is_self_chat' => true,
            ]);
            
            // Add user as only member
            $selfChat->members()->attach($userId);

            // Create welcome message
            $welcomeMessage = \App\Models\Message::create([
                'conversation_id' => $selfChat->id,
                'sender_id' => $userId,
                'body' => 'Welcome to your Saved Messages! Use this chat to save notes, links, files, and anything else you want to keep.',
                'is_system' => true,
            ]);
        }

        return $selfChat;
    }

    /**
     * Generate all possible phone number formats for searching
     * Handles: 0543992073, +233543992073, 233543992073, 543992073, etc.
     */
    protected function getAllPhoneFormats(string $rawPhone, string $normalizedPhone): array
    {
        $formats = [];
        
        // Remove all non-digits to get base digits
        $digits = preg_replace('/\D+/', '', $rawPhone);
        
        // Add the normalized version (with + if present)
        $formats[] = $normalizedPhone;
        
        // If normalized has +, also add without +
        if (str_starts_with($normalizedPhone, '+')) {
            $formats[] = substr($normalizedPhone, 1);
        }
        
        // If it's a Ghana number (starts with 0 and 10 digits, or 9 digits)
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            // Format: 0543992073
            $formats[] = $digits;
            
            // Format: +233543992073 (remove leading 0, add +233)
            $formats[] = '+233' . substr($digits, 1);
            
            // Format: 233543992073 (remove leading 0, add 233)
            $formats[] = '233' . substr($digits, 1);
            
            // Format: 543992073 (just the 9 digits)
            $formats[] = substr($digits, 1);
        }
        
        // If it's 12 digits starting with 233 (country code without +)
        if (strlen($digits) === 12 && str_starts_with($digits, '233')) {
            // Format: 233543992073
            $formats[] = $digits;
            
            // Format: +233543992073
            $formats[] = '+' . $digits;
            
            // Format: 0543992073 (add leading 0)
            $formats[] = '0' . substr($digits, 3);
            
            // Format: 543992073 (just the 9 digits)
            $formats[] = substr($digits, 3);
        }
        
        // If it's 13 digits starting with 233 (with + already in digits)
        if (strlen($digits) === 13 && str_starts_with($digits, '233')) {
            // Format: +233543992073
            $formats[] = '+' . substr($digits, 1);
            
            // Format: 233543992073
            $formats[] = substr($digits, 1);
            
            // Format: 0543992073
            $formats[] = '0' . substr($digits, 4);
            
            // Format: 543992073
            $formats[] = substr($digits, 4);
        }
        
        // If it's 9 digits (just the local number)
        if (strlen($digits) === 9) {
            // Format: 543992073
            $formats[] = $digits;
            
            // Format: 0543992073 (add leading 0)
            $formats[] = '0' . $digits;
            
            // Format: +233543992073
            $formats[] = '+233' . $digits;
            
            // Format: 233543992073
            $formats[] = '233' . $digits;
        }
        
        // Remove duplicates and empty values
        $formats = array_unique(array_filter($formats));
        
        return $formats;
    }
}