<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    /**
     * Update user profile
     * PUT /api/v1/me
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'   => ['nullable', 'string', 'max:60'],
            'about'  => ['nullable', 'string', 'max:160'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'dob_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'dob_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'], // Alternative parameter name
            'day' => ['nullable', 'integer', 'min:1', 'max:31'], // Alternative parameter name
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            try {
                // Delete old avatar if present
                if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                    Storage::disk('public')->delete($user->avatar_path);
                }

                // Store new avatar
                $path = $request->file('avatar')->store('avatars', 'public');
                $user->avatar_path = $path;
            } catch (\Exception $e) {
                Log::error('Avatar upload failed: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload avatar'
                ], 500);
            }
        }

        // Update other fields
        if ($request->filled('name')) {
            $user->name = trim(preg_replace('/\s+/', ' ', $validated['name']));
        }

        if ($request->filled('about')) {
            $user->about = trim($validated['about']);
        }

        // Update birthday (support both parameter names)
        $month = $request->input('dob_month') ?? $request->input('month');
        $day = $request->input('dob_day') ?? $request->input('day');
        
        if ($month !== null) {
            $user->dob_month = (int) $month;
        }
        
        if ($day !== null) {
            $user->dob_day = (int) $day;
        }

        $user->save();
        $user->refresh();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'about' => $user->about,
                'avatar_url' => $user->avatar_url,
                'phone' => $user->phone,
            ]
        ]);
    }
}

