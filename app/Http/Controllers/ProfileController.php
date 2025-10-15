<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function __construct()
    {
        // ensure only logged-in users hit these actions
        $this->middleware('auth');
    }

    public function edit(Request $request)
    {
        // using $request->user() keeps static analysis happy
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'   => ['nullable', 'string', 'max:60'],
            'about'  => ['nullable', 'string', 'max:160'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64'],
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if present
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_path = $path;
        }

        // Only update provided fields (avoid blanking with empty strings)
        if ($request->filled('name')) {
            // Normalize multiple spaces
            $user->name = trim(preg_replace('/\s+/', ' ', $validated['name']));
        }

        if ($request->filled('about')) {
            $user->about = trim($validated['about']);
        }

        $user->save();

        return back()->with('status', 'Profile updated.');
    }
}
