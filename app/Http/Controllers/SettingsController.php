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
use App\Models\UserApiKey;
use App\Models\QuickReply;
use App\Models\UserSession;

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

      // Load conversations – let Conversation::getUnreadCountAttribute() handle unread_count
$conversations = $user->conversations()
    ->with([
        'members:id,name,phone,avatar_path',
        'lastMessage',
    ])
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
                    ->where('user_id', $userId)
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
            $people = User::where('id', '!=', $userId)
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
    // no manual unread_count assignment needed
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

        // Load Quick Replies for Quick Replies tab
        $quickReplies = QuickReply::where('user_id', $user->id)
            ->orderBy('order')
            ->orderBy('created_at', 'desc')
            ->get();

        // Load User Sessions for Devices & Sessions tab
        $currentSessionId = session()->getId();
        // Ensure current session is tracked
        $this->trackCurrentSession($user->id, $currentSessionId);
        
        $sessions = UserSession::where('user_id', $user->id)
            ->orderBy('last_activity', 'desc')
            ->get();

        // If no sessions found, create one for current session
        if ($sessions->isEmpty()) {
            $this->trackCurrentSession($user->id, $currentSessionId);
            $sessions = UserSession::where('user_id', $user->id)
                ->orderBy('last_activity', 'desc')
                ->get();
        }

        return view('settings.index', array_merge(
            compact('user', 'settings', 'forwardDMs', 'forwardGroups', 'quickReplies', 'sessions', 'currentSessionId'),
            $sidebarData
        ));
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        
        $data = $request->validate([
            // Notification settings
            'notifications.browser_notifications' => 'sometimes|boolean',
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
            'account.two_factor_pin' => 'sometimes|nullable|digits:6|required_with:account.two_factor_pin_confirmation',
            'account.two_factor_pin_confirmation' => 'sometimes|nullable|digits:6|same:account.two_factor_pin',
            'account.account_visibility' => 'sometimes|in:public,contacts,nobody',
            
            // Developer mode - accept boolean, string "1"/"0", or integer
            'developer_mode' => 'sometimes',
        ]);

        // Handle developer mode toggle separately (before processing other settings)
        if ($request->has('developer_mode')) {
            $wasEnabled = (bool) $user->developer_mode;
            // Convert string "1"/"0" or boolean to proper boolean
            $developerModeValue = $request->input('developer_mode');
            $isEnabled = filter_var($developerModeValue, FILTER_VALIDATE_BOOLEAN);
            
            $user->developer_mode = $isEnabled;
            
            // Generate unique client_id when developer mode is enabled for the first time
            if ($user->developer_mode && !$wasEnabled && !$user->developer_client_id) {
                $user->developer_client_id = 'dev_' . str_pad($user->id, 8, '0', STR_PAD_LEFT) . '_' . substr(md5($user->id . $user->phone . time()), 0, 16);
            }
            
            // Remove from data array since it's a direct column, not part of JSON settings
            unset($data['developer_mode']);
        }

        // Handle 2FA toggle and PIN setup (before merging settings)
        if (isset($data['account']['two_factor_enabled'])) {
            $isEnabling = $data['account']['two_factor_enabled'];
            $pinProvided = !empty($data['account']['two_factor_pin']);
            
            if ($isEnabling) {
                // If enabling 2FA, require PIN to be set (unless user already has one)
                if (!$pinProvided && !$user->hasTwoFactorPin()) {
                    return back()->withErrors([
                        'account.two_factor_pin' => 'Please set a 6-digit PIN to enable two-factor authentication.'
                    ])->withInput();
                }
                
                // If PIN is provided, set/update it
                if ($pinProvided) {
                    $user->setTwoFactorPin($data['account']['two_factor_pin']);
                }
                // If no PIN provided but user already has one, just keep the existing PIN
            } else {
                // If disabling 2FA, clear the PIN
                $user->clearTwoFactorPin();
            }
            
            // Remove PIN from data array (we've handled it separately)
            unset($data['account']['two_factor_pin']);
            unset($data['account']['two_factor_pin_confirmation']);
        } else {
            // If 2FA toggle not being changed, remove PIN fields from data
            unset($data['account']['two_factor_pin']);
            unset($data['account']['two_factor_pin_confirmation']);
        }

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

        // Return JSON response for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully'
            ]);
        }

        return back()->with('status', 'Settings updated successfully');
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email,' . $user->id,
            'phone'      => 'required|string|unique:users,phone,' . $user->id,
            'username'   => 'nullable|string|min:3|max:20|regex:/^[a-zA-Z0-9_]+$/|unique:users,username,' . $user->id,
            'avatar'     => 'nullable|image|max:2048',
            'bio'        => 'nullable|string|max:500',
            'dob_month'  => 'nullable|integer|min:1|max:12',
            'dob_day'    => 'nullable|integer|min:1|max:31',
        ], [
            'username.regex' => 'Username can only contain letters, numbers, and underscores.',
            'username.unique' => 'This username is already taken. Please choose another one.',
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

        // Assign optional DOB fields separately to ensure nullability
        $user->dob_month = $data['dob_month'] ?? null;
        $user->dob_day   = $data['dob_day'] ?? null;

        // Remove non-column fields before mass update
        unset($data['dob_month'], $data['dob_day']);

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

    /**
     * Generate a new API key (client secret)
     */
    public function generateApiKey(Request $request)
    {
        $user = Auth::user();

        // Check if developer mode is enabled
        if (!$user->developer_mode) {
            return back()->withErrors('Developer mode must be enabled to generate API keys.');
        }

        // Ensure client_id exists
        if (!$user->developer_client_id) {
            $user->developer_client_id = 'dev_' . str_pad($user->id, 8, '0', STR_PAD_LEFT) . '_' . substr(md5($user->id . $user->phone . time()), 0, 16);
            $user->save();
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Generate new client secret using UserApiKey model
        $apiKey = \App\Models\UserApiKey::createForUser($user->id, $data['name']);

        // Return with the plain text secret (only shown once)
        return back()->with('new_api_key', $apiKey->client_secret_plain)
                     ->with('new_api_key_id', $apiKey->id);
    }

    /**
     * Revoke an API key (client secret)
     */
    public function revokeApiKey(Request $request, $tokenId)
    {
        $user = Auth::user();

        // Find and delete the user API key
        $apiKey = $user->userApiKeys()->where('id', $tokenId)->first();

        if (!$apiKey) {
            return back()->withErrors('API key not found.');
        }

        $apiKey->delete();

        return back()->with('status', 'API key revoked successfully.');
    }

    /**
     * Track current session (helper method for devices tab)
     */
    private function trackCurrentSession($userId, $sessionId)
    {
        $request = request();
        $deviceInfo = $this->parseUserAgent($request->userAgent());
        
        UserSession::updateOrCreate(
            [
                'user_id' => $userId,
                'session_id' => $sessionId
            ],
            [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_type' => $deviceInfo['device_type'],
                'browser' => $deviceInfo['browser'],
                'platform' => $deviceInfo['platform'],
                'location' => $this->getLocation($request->ip()),
                'is_current' => true,
                'last_activity' => now(),
            ]
        );
    }

    /**
     * Parse user agent string to get device information
     */
    private function parseUserAgent($userAgent)
    {
        $deviceType = 'desktop';
        $browser = 'Unknown';
        $platform = 'Unknown';

        // Simple device detection
        if (Str::contains($userAgent, ['Mobile', 'Android', 'iPhone', 'iPad'])) {
            $deviceType = Str::contains($userAgent, 'iPad') ? 'tablet' : 'mobile';
        }

        // Browser detection
        if (Str::contains($userAgent, 'Chrome')) {
            $browser = 'Chrome';
        } elseif (Str::contains($userAgent, 'Firefox')) {
            $browser = 'Firefox';
        } elseif (Str::contains($userAgent, 'Safari') && !Str::contains($userAgent, 'Chrome')) {
            $browser = 'Safari';
        } elseif (Str::contains($userAgent, 'Edge')) {
            $browser = 'Edge';
        }

        // Platform detection
        if (Str::contains($userAgent, 'Windows')) {
            $platform = 'Windows';
        } elseif (Str::contains($userAgent, 'Mac')) {
            $platform = 'macOS';
        } elseif (Str::contains($userAgent, 'Linux')) {
            $platform = 'Linux';
        } elseif (Str::contains($userAgent, 'Android')) {
            $platform = 'Android';
        } elseif (Str::contains($userAgent, 'iPhone') || Str::contains($userAgent, 'iPad')) {
            $platform = 'iOS';
        }

        return [
            'device_type' => $deviceType,
            'browser' => $browser,
            'platform' => $platform
        ];
    }

    /**
     * Get location from IP (simplified version)
     */
    private function getLocation($ip)
    {
        // In a real application, you might use a service like ipapi.co or ipinfo.io
        // For now, we'll return a simplified version
        if ($ip === '127.0.0.1' || Str::startsWith($ip, '192.168.') || Str::startsWith($ip, '10.')) {
            return 'Local Network';
        }

        // You can implement proper IP geolocation here
        return 'Unknown Location';
    }
}