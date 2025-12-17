<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = Auth::user() ?? $request->user();

        $validated = $request->validate([
            'name'   => ['nullable', 'string', 'max:60'],
            'about'  => ['nullable', 'string', 'max:160'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64'],
        ]);

        // Debug: Check if file is received
        Log::info('Avatar upload attempt', [
            'has_file' => $request->hasFile('avatar'),
            'file_valid' => $request->file('avatar')?->isValid(),
            'file_size' => $request->file('avatar')?->getSize(),
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            try {
                // Delete old avatar if present
                if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                    Storage::disk('public')->delete($user->avatar_path);
                    Log::info('Deleted old avatar: ' . $user->avatar_path);
                }

                // Store new avatar
                $path = $request->file('avatar')->store('avatars', 'public');
                $user->avatar_path = $path;
                
                Log::info('New avatar stored', ['path' => $path]);
                
            } catch (\Exception $e) {
                Log::error('Avatar upload failed: ' . $e->getMessage());
                return back()->withErrors(['avatar' => 'Failed to upload image.']);
            }
        } else {
            Log::warning('Avatar file missing or invalid');
        }

        // Update other fields
        if ($request->filled('name')) {
            $user->name = trim(preg_replace('/\s+/', ' ', $validated['name']));
        }

        if ($request->filled('about')) {
            $user->about = trim($validated['about']);
        }

        // Save and check if changes were made
        $saved = $user->save();
        
        Log::info('User save result', [
            'saved' => $saved,
            'avatar_path' => $user->avatar_path,
            'changes' => $user->getChanges()
        ]);

        return back()->with('status', 'Profile updated.');
    }
}