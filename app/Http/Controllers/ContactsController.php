<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Conversation;
use App\Models\Block;
use App\Models\GroupMessage;
use App\Services\GoogleContactsService;
use App\Models\MessageStatus;

class ContactsController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth');

    // }

    protected $googleService;

    public function __construct(GoogleContactsService $googleService)
    {
        $this->googleService = $googleService;
        $this->middleware('auth');
    }
    /**
     * Load sidebar data (conversations and groups)
     */
   private function loadSidebarData()
{
    $user   = Auth::user();
    $userId = $user->id;

    $conversations = $user->conversations()
        ->with([
            'members:id,name,phone,avatar_path',
            'lastMessage',
        ])
        ->withCount(['messages as unread_count' => function ($query) use ($userId) {
            $query->where('sender_id', '!=', $userId)
                ->whereDoesntHave('statuses', function ($statusQuery) use ($userId) {
                    $statusQuery->where('user_id', $userId)
                        ->where('status', MessageStatus::STATUS_READ);
                });
        }])
        ->withMax('messages', 'created_at')
        ->orderByDesc('messages_max_created_at')
        ->get();

    $groups = $user->groups()
        ->with([
            'members:id',
            'messages' => function ($q) {
                $q->with('sender:id,name,phone,avatar_path')
                  ->latest()
                  ->limit(1);
            },
        ])
        ->orderByDesc(
            GroupMessage::select('created_at')
                ->whereColumn('group_messages.group_id', 'groups.id')
                ->latest()
                ->take(1)
        )
        ->get();

    return compact('conversations', 'groups');
}


    // public function index()
    // {
    //     $user = Auth::user();

    //     // FIX: Use 'contactUser' instead of 'user'
    //     $contacts = $user->contacts()->with('contactUser')->paginate(20);

    //     $googleConnected = !empty($user->google_access_token);

    //     // Load sidebar data
    //     $sidebarData = $this->loadSidebarData();

    //     return view('contacts.index', array_merge(compact('contacts', 'googleConnected'), $sidebarData));
    // }


public function index()
{
    $user = Auth::user();
    $contacts = $user->contacts()
        ->with('contactUser')
        ->where('is_deleted', false) // Only show non-deleted contacts
        ->orderBy('is_favorite', 'desc')
        ->orderBy('display_name')
        ->get(); // Changed from paginate(2000) to get()

    // Get Google sync status
    $googleStatus = $this->googleService->getSyncStatus($user);

    return view('contacts.index', compact('contacts', 'googleStatus'));
}

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'contact_ids' => 'required|array',
            'contact_ids.*' => 'exists:contacts,id,user_id,' . $request->user()->id
        ]);

        Contact::where('user_id', $request->user()->id)
            ->whereIn('id', $validated['contact_ids'])
            ->delete();

        return response()->json(['success' => true]);
    }

    public function importGoogle(Request $request)
    {
        $user = Auth::user();

        // Check if user has Google OAuth token
        if (empty($user->google_access_token)) {
            return redirect()->route('contacts.index')->withErrors(['google' => 'Google account not connected']);
        }

        try {
            // Fetch contacts from Google People API
            $response = Http::withToken($user->google_access_token)
                ->get('https://people.googleapis.com/v1/people/me/connections', [
                    'personFields' => 'names,phoneNumbers,emailAddresses,photos',
                    'pageSize' => 1000,
                ]);

            if ($response->successful()) {
                $googleContacts = $response->json('connections', []);
                $importedCount = 0;

                foreach ($googleContacts as $googleContact) {
                    $name = $googleContact['names'][0]['displayName'] ?? null;
                    $phoneNumbers = $googleContact['phoneNumbers'] ?? [];
                    $photo = $googleContact['photos'][0]['url'] ?? null;

                    foreach ($phoneNumbers as $phoneNumber) {
                        $phone = $this->normalizePhone($phoneNumber['value']);

                        if ($phone) {
                            // Find or create user
                            $contactUser = User::firstOrCreate(
                                ['phone' => $phone],
                                [
                                    'name' => $name ?? $phone,
                                    'password' => bcrypt(Str::random(16)),
                                ]
                            );

                            // Add to contacts if not already
                            if (!$user->contacts()->where('phone', $phone)->exists()) {
                                $user->contacts()->create([
                                    'user_id' => $contactUser->id,
                                    'phone' => $phone,
                                    'display_name' => $name,
                                    'is_google_contact' => true,
                                    'source' => 'google',
                                ]);
                                $importedCount++;
                            }
                        }
                    }
                }

                return back()->with('status', "Successfully imported {$importedCount} contacts from Google");
            }
        } catch (\Exception $e) {
            return back()->withErrors(['google' => 'Failed to import Google contacts: ' . $e->getMessage()]);
        }

        return back()->withErrors(['google' => 'Failed to import Google contacts']);
    }

    public function syncGoogle(Request $request)
    {
        // Similar to import but removes contacts that no longer exist in Google
        $user = Auth::user();

        // Implementation for sync (remove deleted contacts, add new ones)
        // This would compare existing Google contacts with current ones

        return back()->with('status', 'Google contacts synced successfully');
    }

    private function normalizePhone($phone)
    {
        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        // Format for Ghana numbers
        if (str_starts_with($cleaned, '0')) {
            return '+233' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '233')) {
            return '+' . $cleaned;
        } elseif (str_starts_with($cleaned, '+')) {
            return $cleaned;
        }

        return null;
    }

    /** Try a safe fallback: exact phone first; if not found, last-9-digits when unique. */
    protected function resolveUserByPhone(string $normalized): ?User
    {
        // 1) Exact
        $u = User::query()->where('phone', $normalized)->first();
        if ($u) return $u;

        // 2) Unique last-9 fallback
        $last9 = Contact::last9($normalized);
        if (strlen($last9) < 7) return null; // too short, skip

        $candidates = User::query()
            ->where('phone', 'like', "%{$last9}")
            ->limit(3)
            ->get();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    /** POST /api/v1/contacts/sync
     *  Body: { contacts: [{display_name, phone}], source?: "device"|"manual" }
     *  Returns: {data:{inserted, updated}}
     */
    public function sync(Request $request)
    {
        $owner = $request->user();

        $validated = $request->validate([
            'contacts' => ['required', 'array', 'max:2000'],
            'contacts.*.display_name' => ['nullable', 'string', 'max:190'],
            'contacts.*.phone'        => ['nullable', 'string', 'max:64'],
            'source'                  => ['nullable', 'string', 'max:32'],
        ]);

        $source = $validated['source'] ?? 'device';
        $items  = $validated['contacts'];

        $inserted = 0;
        $updated = 0;

        foreach ($items as $c) {
            $rawPhone = Arr::get($c, 'phone', '');
            $norm     = Contact::normalizePhone($rawPhone);
            if ($norm === '') continue;

            $name = trim((string) Arr::get($c, 'display_name'));
            $resolved = $this->resolveUserByPhone($norm);

            /** @var Contact|null $existing */
            $existing = Contact::query()
                ->where('user_id', $owner->id)
                ->where('normalized_phone', $norm)
                ->first();

            $payload = [
                'display_name'     => $name ?: ($existing->display_name ?? null),
                'phone'            => $rawPhone,
                'normalized_phone' => $norm,
                'source'           => $source,
                'contact_user_id'  => $resolved?->id,
            ];

            if ($existing) {
                $existing->fill($payload)->save();
                $updated++;
            } else {
                Contact::create([
                    'user_id' => $owner->id,
                ] + $payload);
                $inserted++;
            }
        }

        return \App\Support\ApiResponse::data([
            'inserted' => $inserted,
            'updated'  => $updated,
        ]);
    }

    /** GET /api/v1/contacts?q=...  → {data:[...], meta:{...}} */
    public function apiIndex(Request $request)
    {
        $owner = $request->user();
        $q     = trim((string) $request->query('q', ''));

        $contacts = Contact::query()
            ->with(['contactUser:id,name,phone,avatar_path,last_seen_at'])
            ->where('user_id', $owner->id)
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->where('display_name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('normalized_phone', 'like', "%{$q}%")
                        ->orWhereHas('contactUser', function ($userQuery) use ($q) {
                            $userQuery->where('name', 'like', "%{$q}%")
                                ->orWhere('phone', 'like', "%{$q}%");
                        });
                });
            })
            ->orderByRaw('display_name IS NULL')   // nulls last
            ->orderBy('display_name')
            ->orderBy('normalized_phone')
            ->paginate(50);

        $data = $contacts->getCollection()->map(function (Contact $c) {
            $u = $c->contactUser;
            return [
                'id'               => $c->id,
                'display_name'     => $c->display_name,
                'phone'            => $c->phone,
                'normalized_phone' => $c->normalized_phone,
                'is_favorite'      => (bool) $c->is_favorite,
                'is_registered'    => $u !== null,
                'user_id'          => $u?->id,
                'user_name'        => $u?->name,
                'user_phone'       => $u?->phone,
                'avatar_url'       => $u?->avatar_path ? Storage::disk('public')->url($u->avatar_path) : null,
                'last_seen_at'     => optional($u?->last_seen_at)?->toISOString(),
                'online'           => $u?->last_seen_at && $u->last_seen_at->gt(now()->subMinutes(5)),
                'note'             => $c->note,
                'source'           => $c->source,
                'created_at'       => $c->created_at->toISOString(),
                'updated_at'       => $c->updated_at->toISOString(),
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $contacts->currentPage(),
                'last_page'    => $contacts->lastPage(),
                'per_page'     => $contacts->perPage(),
                'total'        => $contacts->total(),
            ],
        ]);
    }
    // In your ContactsController
    public function destroy($id)
    {
        $contact = Contact::where('user_id', auth()->id())->findOrFail($id);

        // Soft delete - mark as deleted but keep the record
        $contact->markAsDeleted();

        return response()->json([
            'success' => true,
            'message' => 'Contact deleted successfully'
        ]);
    }

    public function restore($id)
    {
        $contact = Contact::where('user_id', auth()->id())->findOrFail($id);
        $contact->restore();

        return response()->json([
            'success' => true,
            'message' => 'Contact restored successfully'
        ]);
    }
    // Add this method to your ContactsController
    public function getUserProfile($userId)
    {
        try {
            $user = User::findOrFail($userId);
            $currentUser = auth()->user();

            // Check if user is in contacts
            $contact = $currentUser->contacts()
                ->where('contact_user_id', $userId)
                ->first();

            $isContact = !is_null($contact);

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'avatar_url' => $user->avatar_url,
                    'initial' => $user->initial,
                    'is_online' => $user->is_online,
                    'last_seen_at' => $user->last_seen_at,
                    'created_at' => $user->created_at,
                    'is_contact' => $isContact,
                    'contact_data' => $contact ? [
                        'id' => $contact->id,
                        'display_name' => $contact->display_name,
                        'phone' => $contact->phone,
                        'note' => $contact->note,
                        'is_favorite' => $contact->is_favorite,
                        'created_at' => $contact->created_at
                    ] : null
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    }

    /** POST /api/v1/contacts/resolve {phones:[...]} → which are on GekyChat */
    public function resolve(Request $request)
    {
        $validated = $request->validate([
            'phones'   => ['required', 'array', 'min:1', 'max:1000'],
            'phones.*' => ['string', 'max:64'],
        ]);

        // Normalize + dedupe
        $norms = [];
        foreach ($validated['phones'] as $raw) {
            $n = Contact::normalizePhone($raw);
            if ($n !== '') $norms[$n] = true;
        }
        $norms = array_keys($norms);
        if (!$norms) return \App\Support\ApiResponse::data([]);

        // Exact matches
        $users = User::query()
            ->select('id', 'name', 'phone', 'avatar_path', 'last_seen_at')
            ->whereIn('phone', $norms)
            ->get()
            ->keyBy('phone');

        $out = [];
        foreach ($norms as $p) {
            $u = $users->get($p);

            // If exact not found, try unique last-9 fallback
            if (!$u) {
                $u = $this->resolveUserByPhone($p);
            }

            $out[] = [
                'phone'            => $p,
                'normalized_phone' => $p,
                'is_registered'    => $u !== null,
                'user' => $u ? [
                    'id'         => $u->id,
                    'name'       => $u->name,
                    'phone'      => $u->phone,
                    'avatar_url' => $u->avatar ? Storage::disk('public')->url($u->avatar) : null,
                    'last_seen_at' => $u->last_seen_at?->toISOString(),
                    'online'     => $u->last_seen_at && $u->last_seen_at->gt(now()->subMinutes(5)),
                ] : null,
            ];
        }

        return \App\Support\ApiResponse::data($out);
    }

    /** POST /api/v1/contacts - Create new manual contact */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'contact_user_id' => 'nullable|exists:users,id',
            'note' => 'nullable|string|max:500',
            'is_favorite' => 'boolean'
        ]);

        $authUser = $request->user();

        // Normalize phone number
        $normalizedPhone = Contact::normalizePhone($validated['phone']);

        if (empty($normalizedPhone)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number format'
            ], 422);
        }

        // Check if contact already exists for this user and phone
        $existingContact = $authUser->contacts()
            ->where('normalized_phone', $normalizedPhone)
            ->first();

        if ($existingContact) {
            return response()->json([
                'success' => false,
                'message' => 'Contact with this phone number already exists'
            ], 409);
        }

        // If contact_user_id not provided, try to find user by phone
        if (empty($validated['contact_user_id'])) {
            $user = $this->resolveUserByPhone($normalizedPhone);
            if ($user && $user->id !== $authUser->id) {
                $validated['contact_user_id'] = $user->id;
            }
        }

        // Create new contact
        $contact = Contact::create([
            'user_id' => $authUser->id,
            'contact_user_id' => $validated['contact_user_id'] ?? null,
            'display_name' => $validated['display_name'],
            'phone' => $validated['phone'],
            'normalized_phone' => $normalizedPhone,
            'source' => 'manual',
            'note' => $validated['note'] ?? null,
            'is_favorite' => $validated['is_favorite'] ?? false
        ]);

        // Load relationship for response
        $contact->load('contactUser:id,name,phone,avatar_path,last_seen_at');

        return response()->json([
            'success' => true,
            'message' => 'Contact saved successfully',
            'data' => $this->formatContact($contact)
        ], 201);
    }

    /** GET /api/v1/contacts/{contact} - Show specific contact */
    public function show(Request $request, $id)
    {
        $contact = Contact::with('contactUser:id,name,phone,avatar_path,last_seen_at')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'data' => $this->formatContact($contact)
        ]);
    }

    /** PUT /api/v1/contacts/{contact} - Update contact */
    public function update(Request $request, $id)
    {
        $contact = Contact::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'note' => 'nullable|string|max:500',
            'is_favorite' => 'boolean'
        ]);

        // Handle phone update
        if (isset($validated['phone'])) {
            $normalizedPhone = Contact::normalizePhone($validated['phone']);

            if (empty($normalizedPhone)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number format'
                ], 422);
            }

            // Check for duplicate phone (excluding current contact)
            $duplicate = Contact::where('user_id', $request->user()->id)
                ->where('normalized_phone', $normalizedPhone)
                ->where('id', '!=', $id)
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Another contact with this phone number already exists'
                ], 409);
            }

            $validated['normalized_phone'] = $normalizedPhone;

            // Try to find user for this phone
            $user = $this->resolveUserByPhone($normalizedPhone);

            if ($user && $user->id !== $request->user()->id) {
                $contact->contact_user_id = $user->id;
            } else {
                $contact->contact_user_id = null;
            }
        }

        $contact->update($validated);
        $contact->load('contactUser:id,name,phone,avatar_path,last_seen_at');

        return response()->json([
            'success' => true,
            'message' => 'Contact updated successfully',
            'data' => $this->formatContact($contact)
        ]);
    }

    /** DELETE /api/v1/contacts/{contact} - Remove contact */
    // public function destroy(Request $request, $id)
    // {
    //     $contact = Contact::where('user_id', $request->user()->id)->findOrFail($id);
    //     $contact->delete();

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Contact deleted successfully'
    //     ]);
    // }

    /** POST /api/v1/contacts/{contact}/favorite - Mark contact as favorite */
    public function favorite(Request $request, $id)
    {
        $contact = Contact::where('user_id', $request->user()->id)->findOrFail($id);
        $contact->update(['is_favorite' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Contact added to favorites'
        ]);
    }

    /** DELETE /api/v1/contacts/{contact}/favorite - Remove contact from favorites */
    public function unfavorite(Request $request, $id)
    {
        $contact = Contact::where('user_id', $request->user()->id)->findOrFail($id);
        $contact->update(['is_favorite' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Contact removed from favorites'
        ]);
    }

    /** Format contact for consistent API response */
    private function formatContact(Contact $contact)
    {
        $u = $contact->contactUser;

        return [
            'id'               => $contact->id,
            'display_name'     => $contact->display_name,
            'phone'            => $contact->phone,
            'normalized_phone' => $contact->normalized_phone,
            'is_favorite'      => (bool)$contact->is_favorite,
            'is_registered'    => !is_null($contact->contact_user_id),
            'user_id'          => $u?->id,
            'user_name'        => $u?->name,
            'user_phone'       => $u?->phone,
            'avatar_url'       => $u?->avatar ? Storage::disk('public')->url($u->avatar) : null,
            'last_seen_at'     => optional($u?->last_seen_at)?->toISOString(),
            'online'           => $u?->last_seen_at && $u->last_seen_at->gt(now()->subMinutes(5)),
            'note'             => $contact->note,
            'source'           => $contact->source,
            'created_at'       => $contact->created_at->toISOString(),
            'updated_at'       => $contact->updated_at->toISOString(),
        ];
    }

    public function create()
    {
        // Load sidebar data for the create form view
        $sidebarData = $this->loadSidebarData();
        return view('contacts.create', $sidebarData);
    }

    public function blockcontactstore(Request $request)
    {
        $request->validate([
            'blocked_user_id' => 'required|exists:users,id',
            'reason' => 'nullable|string|max:500',
        ]);

        // Prevent self-blocking
        if ($request->blocked_user_id == auth()->id()) {
            return response()->json(['message' => 'You cannot block yourself.'], 422);
        }

        // Check if already blocked
        $existingBlock = Block::where('blocker_id', auth()->id())
            ->where('blocked_user_id', $request->blocked_user_id)
            ->first();

        if ($existingBlock) {
            return response()->json(['message' => 'User is already blocked.'], 422);
        }

        $block = Block::create([
            'blocker_id' => auth()->id(),
            'blocked_user_id' => $request->blocked_user_id,
            'reason' => $request->reason,
        ]);

        return response()->json([
            'message' => 'User blocked successfully.',
            'block' => $block
        ]);
    }

    public function blockcontactdestroy(User $user)
    {
        $block = Block::where('blocker_id', auth()->id())
            ->where('blocked_user_id', $user->id)
            ->first();

        if (!$block) {
            return response()->json(['message' => 'Block not found.'], 404);
        }

        $block->delete();

        return response()->json(['message' => 'User unblocked successfully.']);
    }

}
