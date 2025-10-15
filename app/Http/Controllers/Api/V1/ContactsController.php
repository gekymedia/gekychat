<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ContactsController extends Controller
{
    /**
     * GET /api/v1/contacts
     * Return the caller's synced contacts.
     */
    public function index(Request $request)
    {
        $contacts = Contact::with('contactUser:id,name,phone,avatar_path')
            ->where('user_id', $request->user()->id)
            ->orderByRaw('LOWER(COALESCE(NULLIF(display_name, ""), normalized_phone))')
            ->limit(2000)
            ->get();

        $data = $contacts->map(function (Contact $c) {
            $u = $c->contactUser;
            return [
                'id'               => $c->id,
                'display_name'     => $c->display_name,
                'phone'            => $c->phone,
                'normalized_phone' => $c->normalized_phone,
                'is_favorite'      => (bool)$c->is_favorite,
                'is_registered'    => !is_null($c->contact_user_id),
                'user_id'          => $u?->id,
                'user_name'        => $u?->name,
                'user_phone'       => $u?->phone,
                'avatar_url'       => $u?->avatar_path ? Storage::disk('public')->url($u->avatar_path) : null,
                'last_seen_at'     => optional($c->last_seen_at)?->toISOString(),
                'source'           => $c->source,
                'updated_at'       => $c->updated_at->toISOString(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/v1/contacts/sync
     * Body: { "contacts": [ { "name": "...", "phone": "..." }, ... ] }
     * Upserts into `contacts` for the current user.
     */
    public function sync(Request $request)
    {
        $payload = $request->validate([
            'contacts'            => ['required','array','min:1'],
            'contacts.*.name'     => ['nullable','string','max:160'],
            'contacts.*.phone'    => ['required','string','max:64'],
        ]);

        $ownerId = $request->user()->id;

        // Normalize & de-duplicate by normalized_phone
        $items = collect($payload['contacts'])
            ->map(fn ($c) => [
                'display_name'     => trim((string)($c['name'] ?? '')),
                'phone'            => trim((string)$c['phone']),
                'normalized_phone' => Contact::normalizePhone($c['phone']),
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

        // Preload possible matching users by exact phone OR last 9 digits
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
                    'source'           => 'device',
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
     * Optional quick lookup: POST /api/v1/contacts/resolve
     * Body: { "phones": ["..."] } -> which of these are registered users?
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
            ->get(['id','name','phone','avatar_path']);

        $data = $users->map(function (User $u) {
            return [
                'id'         => $u->id,
                'name'       => $u->name,
                'phone'      => $u->phone,
                'avatar_url' => $u->avatar_path ? Storage::disk('public')->url($u->avatar_path) : null,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
