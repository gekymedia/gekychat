<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ContactsController extends Controller
{
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
            'contacts' => ['required','array','max:2000'],
            'contacts.*.display_name' => ['nullable','string','max:190'],
            'contacts.*.phone'        => ['nullable','string','max:64'],
            'source'                  => ['nullable','string','max:32'],
        ]);

        $source = $validated['source'] ?? 'device';
        $items  = $validated['contacts'];

        $inserted = 0; $updated = 0;

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
    public function index(Request $request)
    {
        $owner = $request->user();
        $q     = trim((string) $request->query('q', ''));

        $contacts = Contact::query()
            ->with(['contactUser:id,name,phone,avatar,last_seen_at'])
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
                'avatar_url'       => $u?->avatar ? Storage::disk('public')->url($u->avatar) : null,
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

    /** POST /api/v1/contacts/resolve {phones:[...]} → which are on GekyChat */
    public function resolve(Request $request)
    {
        $validated = $request->validate([
            'phones'   => ['required','array','min:1','max:1000'],
            'phones.*' => ['string','max:64'],
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
            ->select('id','name','phone','avatar','last_seen_at')
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
        $contact->load('contactUser:id,name,phone,avatar,last_seen_at');

        return response()->json([
            'success' => true,
            'message' => 'Contact saved successfully',
            'data' => $this->formatContact($contact)
        ], 201);
    }

    /** GET /api/v1/contacts/{contact} - Show specific contact */
    public function show(Request $request, $id)
    {
        $contact = Contact::with('contactUser:id,name,phone,avatar,last_seen_at')
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
        $contact->load('contactUser:id,name,phone,avatar,last_seen_at');

        return response()->json([
            'success' => true,
            'message' => 'Contact updated successfully',
            'data' => $this->formatContact($contact)
        ]);
    }

    /** DELETE /api/v1/contacts/{contact} - Remove contact */
    public function destroy(Request $request, $id)
    {
        $contact = Contact::where('user_id', $request->user()->id)->findOrFail($id);
        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact deleted successfully'
        ]);
    }

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
}