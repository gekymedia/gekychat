<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\User;
use App\Models\Contact;

class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Load sidebar data (conversations, groups, and people) - EXACTLY like ChatController
     */
    private function loadSidebarData()
    {
        $userId = Auth::id();
        $user = Auth::user();

        // Load conversations exactly like ChatController
        $conversations = $user->conversations()
            ->with([
                'members:id,name,phone,avatar_path',
                'lastMessage',
            ]) 
            ->withCount(['messages as unread_count' => function ($query) use ($userId) {
                $query->where('sender_id', '!=', $userId)
                      ->whereNull('read_at');
            }])
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->get();

        // Load groups exactly like ChatController
        $groups = $user->groups()
            ->with([
                'members:id',
                'messages' => function ($q) {
                    $q->with('sender:id,name,phone,avatar_path')->latest()->limit(1);
                },
            ])
            ->orderByDesc(
                GroupMessage::select('created_at')
                    ->whereColumn('group_messages.group_id', 'groups.id')
                    ->latest()
                    ->take(1)
            )
            ->get();

        // Load people data EXACTLY like in your sidebar partial
        $people = collect();
        $hasContactsTable = false;

        try {
            if (Schema::hasTable('contacts')) {
                $hasContactsTable = true;
                $contacts = Contact::query()
                    ->with([
                        'contactUser' => function ($query) {
                            $query->select('id', 'name', 'phone', 'avatar_path');
                        },
                    ])
                    ->where('user_id', auth()->id())
                    ->whereNotNull('contact_user_id')
                    ->orderByRaw('COALESCE(NULLIF(display_name, ""), normalized_phone)')
                    ->get();

                $people = $contacts
                    ->map(function ($contact) {
                        $user = $contact->contactUser;
                        if (!$user) {
                            return null;
                        }

                        return (object) [
                            'id' => $user->id,
                            'name' => $contact->display_name ?: $user->name,
                            'phone' => $user->phone,
                            'avatar_path' => $user->avatar_path,
                            'is_contact' => true,
                        ];
                    })
                    ->filter();
            }
        } catch (\Throwable $e) {
            if (app()->environment('production')) {
                \Log::error('Contacts table query failed', ['error' => $e->getMessage()]);
            }
        }

        // Fallback to users if no contacts found
        if ($people->isEmpty()) {
            $people = User::where('id', '!=', auth()->id())
                ->orderByRaw('COALESCE(NULLIF(name, ""), phone)')
                ->get(['id', 'name', 'phone', 'avatar_path'])
                ->map(function ($user) {
                    return (object) [
                        'id' => $user->id,
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'avatar_path' => $user->avatar_path,
                        'is_contact' => false,
                    ];
                });
        }

        // Load bot conversation if exists
        $botConversation = null;
        $botUser = User::where('phone', '0000000000')->first();
        if ($botUser) {
            $botConversation = Conversation::findOrCreateDirect($user->id, $botUser->id);
            $botConversation->load(['lastMessage']);
            
            // Calculate unread count for bot conversation
            $botConversation->unread_count = $botConversation->messages()
                ->where('sender_id', '!=', $userId)
                ->whereNull('read_at')
                ->count();
        }

        return [
            'conversations' => $conversations,
            'groups' => $groups,
            'people' => $people,
            'botConversation' => $botConversation
        ];
    }

    /**
     * Build forward datasets for sidebar (if needed)
     */
    private function buildForwardDatasets($conversations, $groups)
    {
        $userId = Auth::id();
        
        $forwardDMs = $conversations->map(function ($c) use ($userId) {
            return [
                'id'       => $c->id,
                'slug'     => $c->slug,
                'title'    => $c->title,
                'subtitle' => $c->is_saved_messages ? 'Saved Messages' : ($c->other_user?->phone ?? null),
                'avatar'   => $c->avatar_url,
                'type'     => 'dm',
                'is_saved_messages' => $c->is_saved_messages,
            ];
        })->values();

        $forwardGroups = $groups->map(function ($g) {
            return [
                'id'       => $g->id,
                'slug'     => $g->slug,
                'title'    => $g->name ?? 'Group',
                'subtitle' => $g->type === 'channel' ? 'Public Channel' : 'Private Group',
                'avatar'   => $g->avatar_url,
                'type'     => 'group',
                'is_public' => $g->is_public,
            ];
        })->values();

        return [$forwardDMs, $forwardGroups];
    }

    public function index()
    {
        $user = Auth::user();
        
        // Default settings structure
        $defaultSettings = [
            'notifications' => [
                'message_notifications' => true,
                'group_notifications' => true,
                'sound_enabled' => true,
                'vibration_enabled' => true,
            ],
            'privacy' => [
                'last_seen' => 'everybody',
                'profile_photo' => 'everybody',
                'status' => 'everybody',
                'read_receipts' => true,
                'blocked_users' => [],
            ],
            'chat_settings' => [
                'enter_is_send' => true,
                'media_auto_download' => 'wifi',
                'font_size' => 'medium',
                'wallpaper' => null,
            ],
            'storage' => [
                'network_usage' => 'wifi_only',
                'auto_backup' => false,
                'backup_frequency' => 'weekly',
            ],
            'account' => [
                'two_factor_enabled' => false,
                'account_visibility' => 'public',
            ]
        ];

        // Decode user settings from JSON string to array
        $userSettings = [];
        if (!empty($user->settings)) {
            $userSettings = json_decode($user->settings, true) ?? [];
        }

        // Merge user settings with defaults
        $settings = array_merge($defaultSettings, $userSettings);

        // Load ALL sidebar data exactly like ChatController
        $sidebarData = $this->loadSidebarData();
        
        // Build forward datasets if needed by your sidebar
        [$forwardDMs, $forwardGroups] = $this->buildForwardDatasets(
            $sidebarData['conversations'], 
            $sidebarData['groups']
        );

        return view('settings.index', array_merge(
            compact('user', 'settings', 'forwardDMs', 'forwardGroups'),
            $sidebarData
        ));
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        
        $data = $request->validate([
            // Notification settings
            'notifications.message_notifications' => 'sometimes|boolean',
            'notifications.group_notifications' => 'sometimes|boolean',
            'notifications.sound_enabled' => 'sometimes|boolean',
            'notifications.vibration_enabled' => 'sometimes|boolean',
            
            // Privacy settings
            'privacy.last_seen' => 'sometimes|in:everybody,contacts,nobody',
            'privacy.profile_photo' => 'sometimes|in:everybody,contacts,nobody',
            'privacy.status' => 'sometimes|in:everybody,contacts,nobody',
            'privacy.read_receipts' => 'sometimes|boolean',
            
            // Chat settings
            'chat_settings.enter_is_send' => 'sometimes|boolean',
            'chat_settings.media_auto_download' => 'sometimes|in:wifi,cellular,never',
            'chat_settings.font_size' => 'sometimes|in:small,medium,large',
            
            // Storage settings
            'storage.network_usage' => 'sometimes|in:wifi_only,always,never',
            'storage.auto_backup' => 'sometimes|boolean',
            'storage.backup_frequency' => 'sometimes|in:daily,weekly,monthly',
            
            // Account settings
            'account.two_factor_enabled' => 'sometimes|boolean',
            'account.account_visibility' => 'sometimes|in:public,contacts,nobody',
        ]);

        // Get current settings and decode from JSON
        $currentSettings = [];
        if (!empty($user->settings)) {
            $currentSettings = json_decode($user->settings, true) ?? [];
        }
        
        // Merge new data with existing settings
        $newSettings = array_merge($currentSettings, $data);
        
        // Convert back to JSON string for storage
        $user->settings = json_encode($newSettings);
        $user->save();

        return back()->with('status', 'Settings updated successfully');
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|unique:users,phone,' . $user->id,
            'avatar' => 'nullable|image|max:2048',
            'bio' => 'nullable|string|max:500',
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar_path'] = $path;
        }

        $user->update($data);

        return back()->with('status', 'Profile updated successfully');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|confirmed|min:8',
        ]);

        $user->update([
            'password' => bcrypt($data['password'])
        ]);

        return back()->with('status', 'Password updated successfully');
    }

    /**
     * Delete user account
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'confirm_text' => 'required|in:delete my account',
        ]);

        $user = Auth::user();
        
        // Logout the user
        Auth::logout();
        
        // Delete user data (you might want to soft delete instead)
        $user->delete();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Your account has been deleted successfully.');
    }
}