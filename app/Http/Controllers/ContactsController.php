<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

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

        $p = Contact::query()
            ->with(['contactUser:id,name,phone,avatar'])
            ->where('user_id', $owner->id)
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->where('display_name', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%")
                      ->orWhere('normalized_phone', 'like', "%{$q}%");
                });
            })
            ->orderByRaw('display_name IS NULL')   // nulls last
            ->orderBy('display_name')
            ->orderBy('normalized_phone')
            ->paginate(50);

        $data = $p->getCollection()->map(function (Contact $c) {
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
                'avatar_url'       => $u?->avatar ?? $c->avatar_path,
                'last_seen_at'     => optional($c->last_seen_at)?->toISOString(),
                'updated_at'       => optional($c->updated_at)?->toISOString(),
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $p->currentPage(),
                'last_page'    => $p->lastPage(),
                'per_page'     => $p->perPage(),
                'total'        => $p->total(),
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
            ->select('id','name','phone','avatar')
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
                    'avatar_url' => $u->avatar ?? null,
                ] : null,
            ];
        }

        return \App\Support\ApiResponse::data($out);
    }
}
