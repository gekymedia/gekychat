<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Check if the current API client is CUG or schoolsgh (auto-create enabled)
     */
    protected function isAutoCreateEnabled(Request $request): bool
    {
        /** @var \App\Models\ApiClient|null $client */
        $client = $request->attributes->get('api_client');
        
        if (!$client || !$client->client_id) {
            return false;
        }

        // CUG and schoolsgh platforms can auto-create users
        $clientId = $client->client_id;
        return str_starts_with($clientId, 'cug_platform_') || 
               str_starts_with($clientId, 'schoolsgh_platform_');
    }

    /**
     * Find a user by phone number
     * For CUG and schoolsgh: auto-creates user if not found
     * For other platforms: returns error if user not found
     */
    public function findByPhone(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $normalizedPhone = Contact::normalizePhone($request->phone);

        // Try to find user by normalized phone
        $user = User::where('normalized_phone', $normalizedPhone)
            ->orWhere('phone', $normalizedPhone)
            ->first();

        if (!$user) {
            // Check if auto-create is enabled for this platform
            if ($this->isAutoCreateEnabled($request)) {
                // Auto-create user for CUG and schoolsgh
                $user = User::create([
                    'phone' => $normalizedPhone,
                    'normalized_phone' => $normalizedPhone,
                    'name' => $normalizedPhone, // Default name to phone number
                    'password' => Hash::make(Str::random(32)), // Random password
                    'phone_verified_at' => null, // Not verified yet
                ]);

                return response()->json([
                    'data' => [
                        'id' => $user->id,
                        'phone' => $user->phone,
                        'name' => $user->name,
                        'normalized_phone' => $user->normalized_phone,
                        'auto_created' => true,
                    ]
                ], 201);
            } else {
                // Other platforms: return error
                return response()->json([
                    'error' => 'User not found. Phone number is not registered on GekyChat.',
                    'code' => 'USER_NOT_FOUND',
                ], 404);
            }
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'name' => $user->name,
                'normalized_phone' => $user->normalized_phone ?? Contact::normalizePhone($user->phone),
                'auto_created' => false,
            ]
        ]);
    }

    /**
     * Create a new user (if they don't exist)
     */
    public function create(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'name' => 'nullable|string|max:255',
        ]);

        $normalizedPhone = Contact::normalizePhone($request->phone);

        // Check if user already exists
        $user = User::where('normalized_phone', $normalizedPhone)
            ->orWhere('phone', $normalizedPhone)
            ->first();

        if ($user) {
            return response()->json([
                'data' => [
                    'id' => $user->id,
                    'phone' => $user->phone,
                    'name' => $user->name,
                    'normalized_phone' => $user->normalized_phone ?? Contact::normalizePhone($user->phone),
                    'created' => false,
                ]
            ]);
        }

        // Create new user
        $user = User::create([
            'phone' => $normalizedPhone,
            'normalized_phone' => $normalizedPhone,
            'name' => $request->name ?? $normalizedPhone,
            'password' => Hash::make(Str::random(32)), // Random password, user will need to verify phone
            'phone_verified_at' => null, // Not verified yet
        ]);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'name' => $user->name,
                'normalized_phone' => $user->normalized_phone,
                'created' => true,
            ]
        ], 201);
    }
}
