<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ContactsController extends Controller
{
    /**
     * GET /api/v1/contacts
     * Return the caller's synced contacts with pagination and filtering.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'search' => 'sometimes|string|max:100',
            'favorites_only' => 'sometimes|boolean',
        ]);

        $query = Contact::with(['contactUser:id,name,phone,avatar_path,last_seen_at'])
            ->where('user_id', $request->user()->id);

        // Search filter
        if (!empty($validated['search'])) {
            $searchTerm = '%' . $validated['search'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('display_name', 'LIKE', $searchTerm)
                  ->orWhere('phone', 'LIKE', $searchTerm)
                  ->orWhere('normalized_phone', 'LIKE', $searchTerm)
                  ->orWhereHas('contactUser', function ($userQuery) use ($searchTerm) {
                      $userQuery->where('name', 'LIKE', $searchTerm)
                               ->orWhere('phone', 'LIKE', $searchTerm);
                  });
            });
        }

        // Favorites filter
        if (!empty($validated['favorites_only'])) {
            $query->where('is_favorite', true);
        }

        $contacts = $query->orderByRaw('LOWER(COALESCE(NULLIF(display_name, ""), normalized_phone))')
            ->paginate($validated['per_page'] ?? 50);

        $data = $contacts->map(function (Contact $c) {
            $u = $c->contactUser;
            return [
                'id'               => $c->id,
                'display_name'     => $c->display_name,
                'phone'            => $c->phone,
                'normalized_phone' => $c->normalized_phone,
                'is_favorite'      => (bool)$c->is_favorite,
                'is_registered'    => !is_null($c->contact_user_id),
                'contact_user_id'  => $c->contact_user_id, // Add this for mobile app compatibility
                'contact_user'     => $u ? [ // Add this for mobile app compatibility
                    'id'         => $u->id,
                    'name'       => $u->name,
                    'phone'      => $u->phone,
                    'avatar_url' => $u->avatar_path ? Storage::disk('public')->url($u->avatar_path) : null,
                    'last_seen_at' => optional($u->last_seen_at)?->toISOString(),
                ] : null,
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
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $contacts->currentPage(),
                'per_page' => $contacts->perPage(),
                'total' => $contacts->total(),
                'last_page' => $contacts->lastPage(),
            ]
        ]);
    }

    /**
     * POST /api/v1/contacts
     * Create a new manual contact
     */
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
            $user = User::where('phone', $normalizedPhone)
                ->orWhereRaw('RIGHT(REGEXP_REPLACE(phone, "[^0-9]", ""), 9) = ?', [Contact::last9($normalizedPhone)])
                ->first();
            
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

    /**
     * GET /api/v1/contacts/{contact}
     * Show specific contact
     */
    public function show(Request $request, $id)
    {
        $contact = Contact::with('contactUser:id,name,phone,avatar_path,last_seen_at')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'data' => $this->formatContact($contact)
        ]);
    }

    /**
     * PUT /api/v1/contacts/{contact}
     * Update contact
     */
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
            $user = User::where('phone', $normalizedPhone)
                ->orWhereRaw('RIGHT(REGEXP_REPLACE(phone, "[^0-9]", ""), 9) = ?', [Contact::last9($normalizedPhone)])
                ->first();
            
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

    /**
     * DELETE /api/v1/contacts/{contact}
     * Remove contact
     */
    public function destroy(Request $request, $id)
    {
        $contact = Contact::where('user_id', $request->user()->id)->findOrFail($id);
        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact deleted successfully'
        ]);
    }

    /**
     * POST /api/v1/contacts/{contact}/favorite
     * Mark contact as favorite
     */
    public function favorite(Request $request, $id)
    {
        $contact = Contact::where('user_id', $request->user()->id)->findOrFail($id);
        $contact->update(['is_favorite' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Contact added to favorites'
        ]);
    }

    /**
     * DELETE /api/v1/contacts/{contact}/favorite
     * Remove contact from favorites
     */
    public function unfavorite(Request $request, $id)
    {
        $contact = Contact::where('user_id', $request->user()->id)->findOrFail($id);
        $contact->update(['is_favorite' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Contact removed from favorites'
        ]);
    }

    /**
     * POST /api/v1/contacts/sync
     * Sync device contacts
     */
    public function sync(Request $request)
    {
        $payload = $request->validate([
            'contacts'            => ['required','array','min:1'],
            'contacts.*.name'     => ['nullable','string','max:160'],
            'contacts.*.phone'    => ['required','string','max:64'],
            'contacts.*.source'   => ['nullable','string','max:32'],
        ]);

        $ownerId = $request->user()->id;

        // Normalize & de-duplicate by normalized_phone
        $items = collect($payload['contacts'])
            ->map(fn ($c) => [
                'display_name'     => trim((string)($c['name'] ?? '')),
                'phone'            => trim((string)$c['phone']),
                'normalized_phone' => Contact::normalizePhone($c['phone']),
                'source'           => $c['source'] ?? 'phone', // Default to 'phone' for mobile synced contacts
            ])
            ->filter(fn ($c) => !empty($c['normalized_phone']))
            ->unique('normalized_phone')
            ->values();

        if ($items->isEmpty()) {
            return response()->json([
                'status' => 'ok',
                'upserted' => 0,
                'matched'  => 0,
            ]);
        }

        // Preload possible matching users
        $norms   = $items->pluck('normalized_phone')->all();
        $last9s  = array_unique(array_map(fn($n) => Contact::last9($n), $norms));

        $matchCandidates = User::query()
            ->whereIn('phone', $norms)
            ->orWhere(function ($q) use ($last9s) {
                foreach ($last9s as $l9) {
                    $q->orWhereRaw('RIGHT(REGEXP_REPLACE(phone, "[^0-9]", ""), 9) = ?', [$l9]);
                }
            })
            ->get(['id','name','phone','avatar_path']);

        // Index users by normalized and last9 for fast lookup
        $byPhone = $matchCandidates->keyBy(function ($u) {
            return Contact::normalizePhone($u->phone);
        });
        $byLast9 = $matchCandidates->keyBy(function ($u) {
            return Contact::last9(Contact::normalizePhone($u->phone));
        });

        $upserted = 0; $matched = 0;

        DB::transaction(function () use ($ownerId, $items, $byPhone, $byLast9, &$upserted, &$matched) {
            foreach ($items as $c) {
                $norm = $c['normalized_phone'];
                $candidate = $byPhone->get($norm) ?: $byLast9->get(Contact::last9($norm));

                $values = [
                    'display_name'     => $c['display_name'] ?: null,
                    'phone'            => $c['phone'],
                    'normalized_phone' => $norm,
                    'source'           => $c['source'] ?? 'phone', // Use 'phone' as default for mobile synced contacts
                ];

                // Upsert by (user_id, normalized_phone)
                $existing = Contact::where('user_id', $ownerId)
                    ->where('normalized_phone', $norm)
                    ->first();

                if ($existing) {
                    $existing->fill($values);
                    // update link if found
                    if ($candidate && $candidate->id !== $ownerId) {
                        $existing->contact_user_id = $candidate->id;
                        $matched++;
                    } else {
                        $existing->contact_user_id = null;
                    }
                    $existing->save();
                } else {
                    $create = array_merge($values, ['user_id' => $ownerId]);
                    if ($candidate && $candidate->id !== $ownerId) {
                        $create['contact_user_id'] = $candidate->id;
                        $matched++;
                    }
                    Contact::create($create);
                    $upserted++;
                }
            }
        });

        return response()->json([
            'status'   => 'success',
            'upserted' => $upserted,
            'matched'  => $matched,
        ]);
    }

    /**
     * POST /api/v1/contacts/resolve
     * Resolve phones to registered users
     */
    public function resolve(Request $request)
    {
        $data = $request->validate([
            'phones'   => ['required','array','min:1'],
            'phones.*' => ['string','max:64'],
        ]);

        $norms  = collect($data['phones'])->map([Contact::class, 'normalizePhone'])->filter()->values();
        $last9s = $norms->map([Contact::class, 'last9'])->unique()->values();

        $users = User::query()
            ->whereIn('phone', $norms->all())
            ->orWhere(function ($q) use ($last9s) {
                foreach ($last9s as $l9) {
                    $q->orWhereRaw('RIGHT(REGEXP_REPLACE(phone, "[^0-9]", ""), 9) = ?', [$l9]);
                }
            })
            ->limit(500)
            ->get(['id','name','phone','avatar_path','last_seen_at']);

        $data = $users->map(function (User $u) {
            return [
                'id'         => $u->id,
                'name'       => $u->name,
                'phone'      => $u->phone,
                'avatar_url' => $u->avatar_path ? Storage::disk('public')->url($u->avatar_path) : null,
                'last_seen_at' => $u->last_seen_at?->toISOString(),
                'online'     => $u->last_seen_at && $u->last_seen_at->gt(now()->subMinutes(5)),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Format contact for consistent API response
     */
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
            'avatar_url'       => $u?->avatar_path ? Storage::disk('public')->url($u->avatar_path) : null,
            'last_seen_at'     => optional($u?->last_seen_at)?->toISOString(),
            'online'           => $u?->last_seen_at && $u->last_seen_at->gt(now()->subMinutes(5)),
            'note'             => $contact->note,
            'source'           => $contact->source,
            'created_at'       => $contact->created_at->toISOString(),
            'updated_at'       => $contact->updated_at->toISOString(),
        ];
    }
    
    /**
     * GET /api/v1/contacts/user/{user}/profile
     * Get user profile for contact management
     */
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
                    'avatar_url' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : $user->avatar_url,
                    'initial' => $user->initial,
                    'is_online' => $user->is_online,
                    'last_seen_at' => $user->last_seen_at ? $user->last_seen_at->toISOString() : null,
                    'created_at' => $user->created_at ? $user->created_at->toISOString() : null,
                    'is_contact' => $isContact,
                    'contact_data' => $contact ? [
                        'id' => $contact->id,
                        'display_name' => $contact->display_name,
                        'phone' => $contact->phone,
                        'note' => $contact->note,
                        'is_favorite' => $contact->is_favorite,
                        'created_at' => $contact->created_at->toISOString()
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
}