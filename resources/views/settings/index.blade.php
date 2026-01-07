{{-- settings/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Settings - ' . config('app.name', 'GekyChat'))

@section('content')
<div class="container-fluid h-100">
    <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-card border-bottom border-border py-3">
            <h1 class="h4 mb-0 fw-bold text-text">Settings</h1>
            <p class="text-muted mb-0">Manage your account preferences and privacy</p>
        </div>

        <div class="card-body bg-bg">
            {{-- Status Messages --}}
            @if (session('status'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            {{-- Settings Navigation --}}
            <div class="row h-100">
                <div class="col-lg-3 mb-4">
                    <div class="nav flex-column nav-pills" id="settingsTabs" role="tablist" aria-orientation="vertical">
                        <button class="nav-link active" id="profile-tab" data-bs-toggle="pill" 
                                data-bs-target="#profile" type="button" role="tab" aria-controls="profile" 
                                aria-selected="true">
                            <i class="bi bi-person me-2"></i>Profile
                        </button>
                        <button class="nav-link" id="quick-replies-tab" data-bs-toggle="pill" 
        data-bs-target="#quick-replies" type="button" role="tab" 
        aria-controls="quick-replies" aria-selected="false">
    <i class="bi bi-reply-all me-2"></i>Quick Replies
</button>
                        <button class="nav-link" id="notifications-tab" data-bs-toggle="pill" 
                                data-bs-target="#notifications" type="button" role="tab" 
                                aria-controls="notifications" aria-selected="false">
                            <i class="bi bi-bell me-2"></i>Notifications
                        </button>
                        <button class="nav-link" id="devices-tab" data-bs-toggle="pill" 
        data-bs-target="#devices" type="button" role="tab" 
        aria-controls="devices" aria-selected="false">
    <i class="bi bi-laptop me-2"></i>Devices & Sessions
</button>
                        <button class="nav-link" id="privacy-tab" data-bs-toggle="pill" 
                                data-bs-target="#privacy" type="button" role="tab" 
                                aria-controls="privacy" aria-selected="false">
                            <i class="bi bi-shield-lock me-2"></i>Privacy
                        </button>
                        <button class="nav-link" id="chat-tab" data-bs-toggle="pill" 
                                data-bs-target="#chat" type="button" role="tab" 
                                aria-controls="chat" aria-selected="false">
                            <i class="bi bi-chat-dots me-2"></i>Chat Settings
                        </button>
                        <button class="nav-link" id="storage-tab" data-bs-toggle="pill" 
                                data-bs-target="#storage" type="button" role="tab" 
                                aria-controls="storage" aria-selected="false">
                            <i class="bi bi-device-ssd me-2"></i>Storage
                        </button>
                        <button class="nav-link" id="account-tab" data-bs-toggle="pill" 
                                data-bs-target="#account" type="button" role="tab" 
                                aria-controls="account" aria-selected="false">
                            <i class="bi bi-gear me-2"></i>Account
                        </button>
                        @if($user->developer_mode)
                        <button class="nav-link" id="api-keys-tab" data-bs-toggle="pill" 
                                data-bs-target="#api-keys" type="button" role="tab" 
                                aria-controls="api-keys" aria-selected="false">
                            <i class="bi bi-key me-2"></i>API Keys
                        </button>
                        @endif
                    </div>
                </div>

                <div class="col-lg-9">
                    <div class="tab-content" id="settingsTabsContent">
                        {{-- Profile Tab --}}
                        <div class="tab-pane fade show active" id="profile" role="tabpanel" 
                             aria-labelledby="profile-tab">
                            <form action="{{ route('settings.profile') }}" method="POST" enctype="multipart/form-data">
                                @method('PUT')
                                @csrf
                                <div class="row">
                                    <div class="col-md-4 text-center mb-4">
                                        <div class="avatar-upload position-relative mx-auto">
                                            <img id="avatar-preview" 
                                                 src="{{ $user->avatar_path ? Storage::url($user->avatar_path) : asset('images/avatar-default.png') }}" 
                                                 class="rounded-circle border shadow-sm mb-3" 
                                                 width="120" height="120" 
                                                 alt="Profile picture"
                                                 style="object-fit: cover;">
                                            <label for="avatar" class="btn btn-wa btn-sm position-absolute bottom-0 end-0 rounded-circle" 
                                                   style="width: 36px; height: 36px;">
                                                <i class="bi bi-camera"></i>
                                                <input type="file" id="avatar" name="avatar" 
                                                       accept="image/*" class="d-none" 
                                                       onchange="previewImage(this)">
                                            </label>
                                        </div>
                                        <small class="text-muted">Click camera icon to change photo</small>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="name" class="form-label text-text">Full Name</label>
                                            <input type="text" class="form-control bg-input-bg border-input-border text-text" id="name" name="name" 
                                                   value="{{ old('name', $user->name) }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="email" class="form-label text-text">Email Address</label>
                                            <input type="email" class="form-control bg-input-bg border-input-border text-text" id="email" name="email" 
                                                   value="{{ old('email', $user->email) }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="phone" class="form-label text-text">Phone Number</label>
                                            <input type="tel" class="form-control bg-input-bg border-input-border text-text" id="phone" name="phone" 
                                                   value="{{ old('phone', $user->phone) }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="username" class="form-label text-text">Username</label>
                                            <input type="text" class="form-control bg-input-bg border-input-border text-text" id="username" name="username" 
                                                   value="{{ old('username', $user->username) }}" 
                                                   pattern="[a-zA-Z0-9_]{3,20}" 
                                                   placeholder="e.g., johndoe123">
                                            <div class="form-text text-muted">
                                                @if($user->username)
                                                    Your username: <strong>@{{ $user->username }}</strong> • 
                                                    <a href="{{ route('world-feed.index') }}">View World Feed</a>
                                                @else
                                                    Choose a unique username (3-20 characters, letters, numbers, and underscores only). 
                                                    Required for World Feed, Email Chat, and Live Broadcasts.
                                                @endif
                                            </div>
                                            @error('username')
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="dob_month" class="form-label text-text">Birth Month</label>
                                                <select name="dob_month" id="dob_month" class="form-select bg-input-bg border-input-border text-text">
                                                    <option value="">-- Select month --</option>
                                                    @for ($m = 1; $m <= 12; $m++)
                                                        @php $monthName = \Carbon\Carbon::create()->startOfYear()->addMonths($m - 1)->format('F'); @endphp
                                                        <option value="{{ $m }}" {{ (int) old('dob_month', $user->dob_month) === $m ? 'selected' : '' }}>
                                                            {{ $monthName }}
                                                        </option>
                                                    @endfor
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="dob_day" class="form-label text-text">Birth Day</label>
                                                <select name="dob_day" id="dob_day" class="form-select bg-input-bg border-input-border text-text">
                                                    <option value="">-- Select day --</option>
                                                    @for ($d = 1; $d <= 31; $d++)
                                                        <option value="{{ $d }}" {{ (int) old('dob_day', $user->dob_day) === $d ? 'selected' : '' }}>
                                                            {{ $d }}
                                                        </option>
                                                    @endfor
                                                </select>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="bio" class="form-label text-text">Bio</label>
                                            <textarea class="form-control bg-input-bg border-input-border text-text" id="bio" name="bio" rows="3" 
                                                      placeholder="Tell us about yourself...">{{ old('bio', $user->bio) }}</textarea>
                                            <div class="form-text text-muted">Max 500 characters</div>
                                        </div>
                                        <button type="submit" class="btn btn-wa">Update Profile</button>
                                    </div>
                                </div>
                            </form>
                        </div>
{{-- Quick Replies Tab --}}
<div class="tab-pane fade" id="quick-replies" role="tabpanel" 
     aria-labelledby="quick-replies-tab">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h5 class="mb-0 fw-bold text-text">Quick Replies</h5>
            <p class="text-muted mb-0 small">Save and reuse frequently sent messages</p>
        </div>
        <button class="btn btn-wa btn-sm" data-bs-toggle="modal" data-bs-target="#addQuickReplyModal">
            <i class="bi bi-plus-lg me-1"></i> Add Quick Reply
        </button>
    </div>

    <div id="quick-replies-ajax-alerts-container"></div>

    {{-- Quick Replies List --}}
    <div id="quick-replies-container">
        @if($quickReplies->count() > 0)
            <div class="list-group list-group-flush" id="quick-replies-list">
                @foreach($quickReplies as $reply)
                    <div class="list-group-item quick-reply-item d-flex align-items-center px-0 py-3 bg-card border-border" 
                         data-reply-id="{{ $reply->id }}">
                        
                        {{-- Drag Handle --}}
                        <div class="drag-handle me-3 text-muted cursor-grab">
                            <i class="bi bi-grip-vertical"></i>
                        </div>

                        {{-- Reply Content --}}
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="mb-0 fw-semibold text-text">{{ $reply->title }}</h6>
                                <div class="d-flex align-items-center gap-2">
                                    <small class="text-muted">
                                        <i class="bi bi-chat me-1"></i>
                                        Used {{ $reply->usage_count }} times
                                    </small>
                                    @if($reply->last_used_at)
                                        <small class="text-muted">
                                            Last used {{ $reply->last_used_at->diffForHumans() }}
                                        </small>
                                    @endif
                                </div>
                            </div>
                            <p class="mb-0 text-muted quick-reply-message">{{ $reply->message }}</p>
                        </div>

                        {{-- Actions --}}
                        <div class="dropdown ms-3">
                            <button class="btn btn-sm btn-outline-secondary border-0 text-text" type="button" 
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end bg-card border-border">
                                <li>
                                    <button class="dropdown-item text-text edit-quick-reply-btn" 
                                            data-id="{{ $reply->id }}"
                                            data-title="{{ $reply->title }}"
                                            data-message="{{ $reply->message }}">
                                        <i class="bi bi-pencil me-2"></i>Edit
                                    </button>
                                </li>
                                <li>
                                    <button class="dropdown-item text-text copy-message-btn" 
                                            data-message="{{ $reply->message }}">
                                        <i class="bi bi-clipboard me-2"></i>Copy Message
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider border-border"></li>
                                <li>
                                    <button class="dropdown-item text-danger delete-quick-reply-btn" 
                                            data-id="{{ $reply->id }}" 
                                            data-title="{{ $reply->title }}">
                                        <i class="bi bi-trash me-2"></i>Delete
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- Empty State --}}
            <div class="text-center py-5" id="quick-replies-empty-state">
                <div class="empty-state-icon mb-4">
                    <i class="bi bi-chat-square-text display-1 text-muted"></i>
                </div>
                <h4 class="text-muted mb-3">No quick replies yet</h4>
                <p class="text-muted mb-4">Create your first quick reply to save time when chatting</p>
                <button class="btn btn-wa" data-bs-toggle="modal" data-bs-target="#addQuickReplyModal">
                    <i class="bi bi-plus-lg me-2"></i>Create Your First Quick Reply
                </button>
            </div>
        @endif
    </div>
</div>
                        {{-- Notifications Tab --}}
                        <div class="tab-pane fade" id="notifications" role="tabpanel" 
                             aria-labelledby="notifications-tab">
                            <form action="{{ route('settings.update') }}" method="POST">
                                @method('PUT')
                                @csrf
                                <div class="mb-4">
                                    <h6 class="fw-semibold mb-3 text-text">Push Notifications</h6>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="browser_notifications" 
                                               name="notifications[browser_notifications]" value="1"
                                               {{ ($settings['notifications']['browser_notifications'] ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label text-text" for="browser_notifications">
                                            Browser notifications
                                        </label>
                                        <div class="form-text text-muted">Enable browser push notifications for new messages</div>
                                        <div id="browser_notification_status" class="form-text text-muted mt-1"></div>
                                    </div>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="message_notifications" 
                                               name="notifications[message_notifications]" value="1"
                                               {{ ($settings['notifications']['message_notifications'] ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label text-text" for="message_notifications">
                                            Message notifications
                                        </label>
                                        <div class="form-text text-muted">Receive notifications for new messages</div>
                                    </div>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="group_notifications" 
                                               name="notifications[group_notifications]" value="1"
                                               {{ ($settings['notifications']['group_notifications'] ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label text-text" for="group_notifications">
                                            Group notifications
                                        </label>
                                        <div class="form-text text-muted">Receive notifications for group messages</div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h6 class="fw-semibold mb-3 text-text">Notification Preferences</h6>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="sound_enabled" 
                                               name="notifications[sound_enabled]" value="1"
                                               {{ ($settings['notifications']['sound_enabled'] ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label text-text" for="sound_enabled">
                                            Sound
                                        </label>
                                    </div>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="vibration_enabled" 
                                               name="notifications[vibration_enabled]" value="1"
                                               {{ ($settings['notifications']['vibration_enabled'] ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label text-text" for="vibration_enabled">
                                            Vibration
                                        </label>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-wa" id="save-notifications-btn">Save Notification Settings</button>
                            </form>
                        </div>
{{-- Devices & Sessions Tab --}}
<div class="tab-pane fade" id="devices" role="tabpanel" 
     aria-labelledby="devices-tab">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h5 class="mb-0 fw-bold text-text">Devices & Sessions</h5>
            <p class="text-muted mb-0 small">Manage your active sessions across devices</p>
        </div>
        <button class="btn btn-outline-danger btn-sm" id="logoutAllOtherBtn">
            <i class="bi bi-laptop me-1"></i> Log Out All Other Devices
        </button>
    </div>

    <div id="devices-ajax-alerts-container"></div>

    {{-- Current Device --}}
    <div class="mb-4">
        <h6 class="fw-semibold text-text mb-3">Current Session</h6>
        @php
            $currentSession = $sessions->where('session_id', $currentSessionId)->first();
            if (!$currentSession && $sessions->isNotEmpty()) {
                $currentSession = $sessions->first();
            }
        @endphp
        @if($currentSession)
            <div class="card bg-success bg-opacity-10 border-success border-opacity-25">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-success bg-opacity-25 rounded-circle p-3">
                                <i class="bi bi-laptop text-success" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 text-text">
                                    {{ $currentSession->platform }} • {{ $currentSession->browser }}
                                    <span class="badge bg-success ms-2">Current</span>
                                </h6>
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <small class="text-muted">
                                        <i class="bi bi-geo-alt me-1"></i>
                                        {{ $currentSession->location ?? 'Unknown location' }}
                                    </small>
                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i>
                                        Active now
                                    </small>
                                    <small class="text-muted">
                                        <i class="bi bi-wifi me-1"></i>
                                        {{ $currentSession->ip_address }}
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <small class="text-success d-block">
                                <i class="bi bi-check-circle me-1"></i>
                                This device
                            </small>
                            <small class="text-muted">
                                Last activity: Now
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="card bg-card border-border">
                <div class="card-body text-center py-4">
                    <i class="bi bi-laptop display-4 text-muted mb-3"></i>
                    <p class="text-muted mb-0">Current session information not available</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Other Active Sessions --}}
    <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="fw-semibold text-text mb-0">Other Active Sessions</h6>
            <small class="text-muted">
                {{ $sessions->where('session_id', '!=', $currentSessionId)->count() }} devices
            </small>
        </div>

        @if($sessions->where('session_id', '!=', $currentSessionId)->count() > 0)
            <div class="list-group list-group-flush" id="sessions-list">
                @foreach($sessions->where('session_id', '!=', $currentSessionId) as $session)
                    <div class="list-group-item session-item d-flex align-items-center px-0 py-3 bg-card border-border" 
                         data-session-id="{{ $session->session_id }}">
                        
                        {{-- Device Icon --}}
                        <div class="me-3">
                            <span style="font-size: 1.5rem;">{{ $session->getDeviceIcon() }}</span>
                        </div>

                        {{-- Session Info --}}
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <h6 class="mb-0 fw-semibold text-text">
                                    {{ $session->platform }} • {{ $session->browser }}
                                    @if($session->is_active)
                                        <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 ms-2">Active</span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-25 text-secondary border border-secondary border-opacity-50 ms-2">Inactive</span>
                                    @endif
                                </h6>
                                <div class="d-flex align-items-center gap-2">
                                    <small class="text-muted">
                                        {{ $session->last_activity_human }}
                                    </small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <small class="text-muted">
                                    <i class="bi bi-geo-alt me-1"></i>
                                    {{ $session->location ?? 'Unknown location' }}
                                </small>
                                <small class="text-muted">
                                    <i class="bi {{ $session->getPlatformIcon() }} me-1"></i>
                                    {{ $session->device_type }}
                                </small>
                                <small class="text-muted">
                                    <i class="bi bi-wifi me-1"></i>
                                    {{ $session->ip_address }}
                                </small>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="dropdown ms-3">
                            <button class="btn btn-sm btn-outline-secondary border-0 text-text" type="button" 
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end bg-card border-border">
                                <li>
                                    <button class="dropdown-item text-danger logout-session-btn" 
                                            data-session-id="{{ $session->session_id }}"
                                            data-platform="{{ $session->platform }}"
                                            data-browser="{{ $session->browser }}">
                                        <i class="bi bi-power me-2"></i>Log Out This Device
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- No Other Sessions --}}
            <div class="card bg-card border-border">
                <div class="card-body text-center py-5">
                    <div class="empty-state-icon mb-4">
                        <i class="bi bi-phone display-1 text-muted"></i>
                    </div>
                    <h5 class="text-muted mb-3">No other active sessions</h5>
                    <p class="text-muted mb-0">You're only logged in on this device</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Session Security Tips --}}
    <div class="card bg-warning bg-opacity-10 border-warning border-opacity-25">
        <div class="card-body">
            <div class="d-flex align-items-start gap-3">
                <i class="bi bi-shield-check text-warning mt-1"></i>
                <div>
                    <h6 class="fw-semibold text-text mb-2">Session Security</h6>
                    <ul class="list-unstyled text-muted small mb-0">
                        <li class="mb-1"><i class="bi bi-check-circle me-2 text-success"></i> Regularly review your active sessions</li>
                        <li class="mb-1"><i class="bi bi-check-circle me-2 text-success"></i> Log out from devices you no longer use</li>
                        <li class="mb-1"><i class="bi bi-check-circle me-2 text-success"></i> Use strong, unique passwords</li>
                        <li class="mb-0"><i class="bi bi-check-circle me-2 text-success"></i> Enable two-factor authentication if available</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
                        {{-- Privacy Tab --}}
                        <div class="tab-pane fade" id="privacy" role="tabpanel" 
                             aria-labelledby="privacy-tab">
                            <form action="{{ route('settings.update') }}" method="POST">
                                @method('PUT')
                                @csrf
                                <div class="mb-4">
                                    <h6 class="fw-semibold mb-3 text-text">Privacy Settings</h6>
                                    <div class="mb-3">
                                        <label for="last_seen" class="form-label text-text">Last Seen & Online</label>
                                        <select class="form-select bg-input-bg border-input-border text-text" id="last_seen" name="privacy[last_seen]">
                                            <option value="everybody" {{ ($settings['privacy']['last_seen'] ?? 'everybody') === 'everybody' ? 'selected' : '' }}>
                                                Everybody
                                            </option>
                                            <option value="contacts" {{ ($settings['privacy']['last_seen'] ?? 'everybody') === 'contacts' ? 'selected' : '' }}>
                                                My Contacts
                                            </option>
                                            <option value="nobody" {{ ($settings['privacy']['last_seen'] ?? 'everybody') === 'nobody' ? 'selected' : '' }}>
                                                Nobody
                                            </option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="profile_photo" class="form-label text-text">Profile Photo</label>
                                        <select class="form-select bg-input-bg border-input-border text-text" id="profile_photo" name="privacy[profile_photo]">
                                            <option value="everybody" {{ ($settings['privacy']['profile_photo'] ?? 'everybody') === 'everybody' ? 'selected' : '' }}>
                                                Everybody
                                            </option>
                                            <option value="contacts" {{ ($settings['privacy']['profile_photo'] ?? 'everybody') === 'contacts' ? 'selected' : '' }}>
                                                My Contacts
                                            </option>
                                            <option value="nobody" {{ ($settings['privacy']['profile_photo'] ?? 'everybody') === 'nobody' ? 'selected' : '' }}>
                                                Nobody
                                            </option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="status" class="form-label text-text">Status</label>
                                        <select class="form-select bg-input-bg border-input-border text-text" id="status" name="privacy[status]">
                                            <option value="everybody" {{ ($settings['privacy']['status'] ?? 'everybody') === 'everybody' ? 'selected' : '' }}>
                                                Everybody
                                            </option>
                                            <option value="contacts" {{ ($settings['privacy']['status'] ?? 'everybody') === 'contacts' ? 'selected' : '' }}>
                                                My Contacts
                                            </option>
                                            <option value="nobody" {{ ($settings['privacy']['status'] ?? 'everybody') === 'nobody' ? 'selected' : '' }}>
                                                Nobody
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="read_receipts" 
                                               name="privacy[read_receipts]" value="1"
                                               {{ ($settings['privacy']['read_receipts'] ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label text-text" for="read_receipts">
                                            Read receipts
                                        </label>
                                        <div class="form-text text-muted">Let others see when you've read their messages</div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-wa">Save Privacy Settings</button>
                            </form>
                        </div>

                        {{-- Chat Settings Tab --}}
                        <div class="tab-pane fade" id="chat" role="tabpanel" 
                             aria-labelledby="chat-tab">
                            <form action="{{ route('settings.update') }}" method="POST">
                                @method('PUT')
                                @csrf
                                <div class="mb-4">
                                    <h6 class="fw-semibold mb-3 text-text">Message Settings</h6>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="enter_is_send" 
                                               name="chat_settings[enter_is_send]" value="1"
                                               {{ ($settings['chat_settings']['enter_is_send'] ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label text-text" for="enter_is_send">
                                            Enter key sends message
                                        </label>
                                        <div class="form-text text-muted">When enabled, press Enter to send. When disabled, use Ctrl+Enter</div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h6 class="fw-semibold mb-3 text-text">Media & Files</h6>
                                    <div class="mb-3">
                                        <label for="media_auto_download" class="form-label text-text">Auto-download Media</label>
                                        <select class="form-select bg-input-bg border-input-border text-text" id="media_auto_download" name="chat_settings[media_auto_download]">
                                            <option value="wifi" {{ ($settings['chat_settings']['media_auto_download'] ?? 'wifi') === 'wifi' ? 'selected' : '' }}>
                                                Wi-Fi only
                                            </option>
                                            <option value="cellular" {{ ($settings['chat_settings']['media_auto_download'] ?? 'wifi') === 'cellular' ? 'selected' : '' }}>
                                                Wi-Fi and cellular
                                            </option>
                                            <option value="never" {{ ($settings['chat_settings']['media_auto_download'] ?? 'wifi') === 'never' ? 'selected' : '' }}>
                                                Never
                                            </option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="font_size" class="form-label text-text">Font Size</label>
                                        <select class="form-select bg-input-bg border-input-border text-text" id="font_size" name="chat_settings[font_size]">
                                            <option value="small" {{ ($settings['chat_settings']['font_size'] ?? 'medium') === 'small' ? 'selected' : '' }}>
                                                Small
                                            </option>
                                            <option value="medium" {{ ($settings['chat_settings']['font_size'] ?? 'medium') === 'medium' ? 'selected' : '' }}>
                                                Medium
                                            </option>
                                            <option value="large" {{ ($settings['chat_settings']['font_size'] ?? 'medium') === 'large' ? 'selected' : '' }}>
                                                Large
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-wa">Save Chat Settings</button>
                            </form>
                        </div>

                        {{-- Storage Tab --}}
                        <div class="tab-pane fade" id="storage" role="tabpanel" 
                             aria-labelledby="storage-tab">
                            <form action="{{ route('settings.update') }}" method="POST">
                                @method('PUT')
                                @csrf
                                <div class="mb-4">
                                    <h6 class="fw-semibold mb-3 text-text">Network Usage</h6>
                                    <div class="mb-3">
                                        <label for="network_usage" class="form-label text-text">Media Download</label>
                                        <select class="form-select bg-input-bg border-input-border text-text" id="network_usage" name="storage[network_usage]">
                                            <option value="wifi_only" {{ ($settings['storage']['network_usage'] ?? 'wifi_only') === 'wifi_only' ? 'selected' : '' }}>
                                                Wi-Fi only
                                            </option>
                                            <option value="always" {{ ($settings['storage']['network_usage'] ?? 'wifi_only') === 'always' ? 'selected' : '' }}>
                                                Always
                                            </option>
                                            <option value="never" {{ ($settings['storage']['network_usage'] ?? 'wifi_only') === 'never' ? 'selected' : '' }}>
                                                Never
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h6 class="fw-semibold mb-3 text-text">Backup</h6>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="auto_backup" 
                                               name="storage[auto_backup]" value="1"
                                               {{ ($settings['storage']['auto_backup'] ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label text-text" for="auto_backup">
                                            Auto backup
                                        </label>
                                    </div>
                                    <div class="mb-3">
                                        <label for="backup_frequency" class="form-label text-text">Backup Frequency</label>
                                        <select class="form-select bg-input-bg border-input-border text-text" id="backup_frequency" name="storage[backup_frequency]">
                                            <option value="daily" {{ ($settings['storage']['backup_frequency'] ?? 'weekly') === 'daily' ? 'selected' : '' }}>
                                                Daily
                                            </option>
                                            <option value="weekly" {{ ($settings['storage']['backup_frequency'] ?? 'weekly') === 'weekly' ? 'selected' : '' }}>
                                                Weekly
                                            </option>
                                            <option value="monthly" {{ ($settings['storage']['backup_frequency'] ?? 'weekly') === 'monthly' ? 'selected' : '' }}>
                                                Monthly
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-wa">Save Storage Settings</button>
                            </form>
                        </div>

                        {{-- Account Tab --}}
                        <div class="tab-pane fade" id="account" role="tabpanel" 
                             aria-labelledby="account-tab">
                            <form action="{{ route('settings.update') }}" method="POST">
                                @method('PUT')
                                @csrf
                                <div class="mb-4">
                                    <h6 class="fw-semibold mb-3 text-text">Security</h6>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="two_factor_enabled" 
                                               name="account[two_factor_enabled]" value="1"
                                               {{ ($settings['account']['two_factor_enabled'] ?? false) ? 'checked' : '' }}
                                               onchange="toggleTwoFactorPin(this.checked)">
                                        <label class="form-check-label text-text" for="two_factor_enabled">
                                            Two-factor authentication
                                            <small class="d-block text-muted">Require a PIN after phone login (like WhatsApp)</small>
                                        </label>
                                    </div>
                                    
                                    {{-- PIN Setup (shown when enabling or already enabled) --}}
                                    <div id="two_factor_pin_section" style="display: {{ ($settings['account']['two_factor_enabled'] ?? false) ? 'block' : 'none' }};">
                                        <div class="mb-3">
                                            <label for="two_factor_pin" class="form-label text-text">
                                                {{ $user->hasTwoFactorPin() ? 'Change PIN (6 digits)' : 'Set PIN (6 digits)' }}
                                            </label>
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="two_factor_pin" 
                                                   name="account[two_factor_pin]" 
                                                   maxlength="6" 
                                                   pattern="\d{6}"
                                                   inputmode="numeric"
                                                   placeholder="000000"
                                                   style="letter-spacing: 0.3em; text-align: center;">
                                            <small class="form-text text-muted">
                                                {{ $user->hasTwoFactorPin() ? 'Leave blank to keep current PIN, or enter a new one to change it.' : 'Set a 6-digit PIN to enable two-factor authentication.' }}
                                            </small>
                                        </div>
                                        <div class="mb-3">
                                            <label for="two_factor_pin_confirmation" class="form-label text-text">Confirm PIN</label>
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="two_factor_pin_confirmation" 
                                                   name="account[two_factor_pin_confirmation]" 
                                                   maxlength="6" 
                                                   pattern="\d{6}"
                                                   inputmode="numeric"
                                                   placeholder="000000"
                                                   style="letter-spacing: 0.3em; text-align: center;">
                                        </div>
                                    </div>
                                    <div class="form-check form-switch mb-3">
                                        {{-- Hidden input for developer_mode in the main form --}}
                                        <input type="hidden" name="developer_mode" id="developer_mode_input" value="{{ $user->developer_mode ? '1' : '0' }}">
                                        <input class="form-check-input" type="checkbox" id="developer_mode" 
                                               {{ $user->developer_mode ? 'checked' : '' }}
                                               onchange="toggleDeveloperMode(this.checked)">
                                        <label class="form-check-label text-text" for="developer_mode">
                                            Developer Mode
                                            <small class="d-block text-muted">Enable API access and manage API keys</small>
                                        </label>
                                    </div>
                                    <button type="submit" class="btn btn-wa">Save Security Settings</button>
                                </div>
                            </form>

                            <form action="{{ route('settings.update') }}" method="POST">
                                @method('PUT')
                                @csrf
                                <div class="mb-4">
                                    <h6 class="fw-semibold mb-3 text-text">Account Visibility</h6>
                                    <div class="mb-3">
                                        <label for="account_visibility" class="form-label text-text">Who can find me</label>
                                        <select class="form-select bg-input-bg border-input-border text-text" id="account_visibility" name="account[account_visibility]">
                                            <option value="public" {{ ($settings['account']['account_visibility'] ?? 'public') === 'public' ? 'selected' : '' }}>
                                                Everybody
                                            </option>
                                            <option value="contacts" {{ ($settings['account']['account_visibility'] ?? 'public') === 'contacts' ? 'selected' : '' }}>
                                                My Contacts
                                            </option>
                                            <option value="nobody" {{ ($settings['account']['account_visibility'] ?? 'public') === 'nobody' ? 'selected' : '' }}>
                                                Nobody
                                            </option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-wa">Save Visibility Settings</button>
                                </div>
                            </form>

                            <div class="border-top border-border pt-4">
                                <h6 class="fw-semibold mb-3 text-danger">Danger Zone</h6>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-danger" data-bs-toggle="modal" 
                                            data-bs-target="#deleteAccountModal">
                                        Delete Account
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- API Keys Tab --}}
                        @if($user->developer_mode)
                        <div class="tab-pane fade" id="api-keys" role="tabpanel" 
                             aria-labelledby="api-keys-tab">
                            <div class="mb-4">
                                <h6 class="fw-semibold mb-3 text-text">API Keys</h6>
                                <p class="text-muted mb-4">Manage your API keys for accessing GekyChat API programmatically.</p>
                                
                                {{-- Client ID Display --}}
                                @if($user->developer_client_id)
                                <div class="card bg-card border-border mb-4">
                                    <div class="card-body">
                                        <h6 class="fw-semibold text-text mb-3">Your Client ID</h6>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-input-bg border-input-border text-text font-monospace" 
                                                   id="client_id_display" value="{{ $user->developer_client_id }}" readonly>
                                            <button class="btn btn-outline-secondary" type="button" onclick="copyClientId(this)">
                                                <i class="bi bi-clipboard"></i> Copy
                                            </button>
                                        </div>
                                        <small class="text-muted mt-2 d-block">This is your unique Client ID. Use it with your Client Secrets (API Keys) below.</small>
                                    </div>
                                </div>
                                @else
                                <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-25 mb-4">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <strong>Client ID not generated yet.</strong> It will be automatically created when you generate your first API key.
                                </div>
                                @endif

                                {{-- Generate New Key Button --}}
                                <div class="mb-4">
                                    <button type="button" class="btn btn-wa" data-bs-toggle="modal" data-bs-target="#generateKeyModal">
                                        <i class="bi bi-plus-circle me-2"></i>Generate New API Key
                                    </button>
                                </div>

                                {{-- API Keys List --}}
                                <div class="card bg-card border-border">
                                    <div class="card-body">
                                        @if($user->userApiKeys->isEmpty())
                                            <div class="text-center py-5">
                                                <i class="bi bi-key text-muted" style="font-size: 3rem;"></i>
                                                <p class="text-muted mt-3">No API keys yet</p>
                                                <p class="text-muted small">Generate your first API key to get started</p>
                                            </div>
                                        @else
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-text">Name</th>
                                                            <th class="text-text">Client Secret</th>
                                                            <th class="text-text">Status</th>
                                                            <th class="text-text">Created</th>
                                                            <th class="text-text">Last Used</th>
                                                            <th class="text-text">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($user->userApiKeys as $apiKey)
                                                        <tr>
                                                            <td class="text-text">{{ $apiKey->name }}</td>
                                                            <td class="text-text">
                                                                <code class="bg-input-bg px-2 py-1 rounded font-monospace">
                                                                    {{ $apiKey->client_secret_plain ? substr($apiKey->client_secret_plain, 0, 12) . '...' . substr($apiKey->client_secret_plain, -8) : '••••••••••••••••' }}
                                                                </code>
                                                            </td>
                                                            <td>
                                                                @if($apiKey->is_active)
                                                                    <span class="badge bg-success">Active</span>
                                                                @else
                                                                    <span class="badge bg-secondary">Inactive</span>
                                                                @endif
                                                            </td>
                                                            <td class="text-muted small">{{ $apiKey->created_at->diffForHumans() }}</td>
                                                            <td class="text-muted small">{{ $apiKey->last_used_at ? $apiKey->last_used_at->diffForHumans() : 'Never' }}</td>
                                                            <td>
                                                                <form action="{{ route('settings.api-keys.revoke', $apiKey->id) }}" method="POST" class="d-inline">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                                            onclick="return confirm('Are you sure you want to revoke this API key? This action cannot be undone.')">
                                                                        <i class="bi bi-trash"></i> Revoke
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- API Documentation Link --}}
                                <div class="alert alert-info bg-info bg-opacity-10 border-info border-opacity-25 mt-4">
                                    <div class="d-flex align-items-start gap-3">
                                        <i class="bi bi-info-circle text-info mt-1"></i>
                                        <div>
                                            <h6 class="fw-semibold text-text mb-2">API Documentation</h6>
                                            <p class="text-muted small mb-2">Learn how to use the GekyChat API in your applications.</p>
                                            <a href="{{ route('api.docs') }}" class="btn btn-sm btn-outline-info" target="_blank">
                                                View Documentation <i class="bi bi-box-arrow-up-right ms-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Generate API Key Modal --}}
<div class="modal fade" id="generateKeyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-card border-border">
            <div class="modal-header border-border">
                <h5 class="modal-title fw-bold text-text">Generate New API Key</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('settings.api-keys.generate') }}" method="POST">
                @csrf
                <div class="modal-body text-text">
                    <div class="mb-3">
                        <label for="token_name" class="form-label">API Key Name</label>
                        <input type="text" class="form-control bg-input-bg border-input-border text-text" 
                               id="token_name" name="name" 
                               placeholder="e.g., My Mobile App" required>
                        <div class="form-text text-muted">Choose a descriptive name to identify this key</div>
                    </div>
                    <div class="alert alert-warning bg-warning border-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Important:</strong> Make sure to copy your API key now. You won't be able to see it again!
                    </div>
                </div>
                <div class="modal-footer border-border">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-wa">Generate Key</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Display New API Key Modal --}}
@if(session('new_api_key'))
<div class="modal fade" id="displayKeyModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-card border-border">
            <div class="modal-header border-border bg-success bg-opacity-10">
                <h5 class="modal-title fw-bold text-success">
                    <i class="bi bi-check-circle-fill me-2"></i>API Key Generated Successfully
                </h5>
            </div>
            <div class="modal-body text-text">
                <p class="mb-3">Your new API key has been generated. Copy it now - you won't be able to see it again!</p>
                <div class="mb-3">
                    <label class="form-label text-text">Client ID</label>
                    <div class="input-group mb-2">
                        <input type="text" class="form-control bg-input-bg border-input-border text-text font-monospace" 
                               id="client_id_copy" value="{{ $user->developer_client_id }}" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyClientId(this)">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-text">Client Secret (API Key)</label>
                    <div class="input-group mb-2">
                        <input type="text" class="form-control bg-input-bg border-input-border text-text font-monospace" 
                               id="new_api_key_display" value="{{ session('new_api_key') }}" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyApiKey(this)">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                    <small class="text-muted">⚠️ Copy this now - you won't be able to see it again!</small>
                </div>
                <div class="alert alert-info bg-info bg-opacity-10 border-info border-opacity-25">
                    <i class="bi bi-info-circle me-2"></i>
                    <small>Store this key securely. Anyone with this key can access your account via the API.</small>
                </div>
            </div>
            <div class="modal-footer border-border">
                <button type="button" class="btn btn-wa" data-bs-dismiss="modal">I've Saved My Key</button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Delete Account Modal --}}
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-card border-border">
            <div class="modal-header border-border">
                <h5 class="modal-title fw-bold text-danger">Delete Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-text">
                <div class="alert alert-warning bg-warning border-warning">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. All your data will be permanently deleted.
                </div>
                <p>Are you sure you want to delete your account? This will:</p>
                <ul>
                    <li>Permanently delete all your messages</li>
                    <li>Remove you from all groups</li>
                    <li>Delete your contact information</li>
                    <li>Remove all your media files</li>
                </ul>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="confirmDelete">
                    <label class="form-check-label text-text" for="confirmDelete">
                        I understand this action is irreversible
                    </label>
                </div>
            </div>
            <div class="modal-footer border-border">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="deleteAccountBtn" disabled>
                    Delete My Account
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Image preview for avatar upload
function previewImage(input) {
    const preview = document.getElementById('avatar-preview');
    const file = input.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
}

// Copy API Key to clipboard
function copyClientId(buttonElement) {
    const clientIdInput = document.getElementById('client_id_display') || document.getElementById('client_id_copy');
    if (!clientIdInput) {
        alert('Client ID input not found');
        return;
    }
    
    clientIdInput.select();
    clientIdInput.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(clientIdInput.value).then(function() {
        const btn = buttonElement || document.querySelector('button[onclick*="copyClientId"]');
        if (btn) {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
            btn.classList.add('btn-success');
            btn.classList.remove('btn-outline-secondary');
            setTimeout(function() {
                btn.innerHTML = originalHTML;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-outline-secondary');
            }, 2000);
        }
    }).catch(function(err) {
        console.error('Copy failed:', err);
        alert('Failed to copy: ' + (err.message || 'Unknown error'));
    });
}

function copyApiKey(buttonElement) {
    const apiKeyInput = document.getElementById('new_api_key_display');
    if (!apiKeyInput) {
        alert('API key input not found');
        return;
    }
    
    apiKeyInput.select();
    apiKeyInput.setSelectionRange(0, 99999); // For mobile devices
    
    navigator.clipboard.writeText(apiKeyInput.value).then(function() {
        // Show success feedback
        const btn = buttonElement || document.querySelector('button[onclick*="copyApiKey"]');
        if (btn) {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
            btn.classList.add('btn-success');
            btn.classList.remove('btn-outline-secondary');
            
            setTimeout(function() {
                btn.innerHTML = originalHTML;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-outline-secondary');
            }, 2000);
        }
    }).catch(function(err) {
        console.error('Copy failed:', err);
        alert('Failed to copy: ' + (err.message || 'Unknown error'));
    });
}

// Toggle Developer Mode
function toggleTwoFactorPin(enabled) {
    const pinSection = document.getElementById('two_factor_pin_section');
    const pinInput = document.getElementById('two_factor_pin');
    const pinConfirm = document.getElementById('two_factor_pin_confirmation');
    
    if (enabled) {
        pinSection.style.display = 'block';
        // PIN is only required if user doesn't already have one
        // We'll validate this on the server side
        if (pinInput) pinInput.required = false; // Not required in HTML, validated server-side
        if (pinConfirm) pinConfirm.required = false;
    } else {
        pinSection.style.display = 'none';
        if (pinInput) {
            pinInput.value = '';
            pinInput.required = false;
        }
        if (pinConfirm) {
            pinConfirm.value = '';
            pinConfirm.required = false;
        }
    }
}

function toggleDeveloperMode(enabled) {
    // Update the hidden input value in the main form
    // The value will be saved when "Save Security Settings" is clicked
    const input = document.getElementById('developer_mode_input');
    if (input) {
        input.value = enabled ? '1' : '0';
        console.log('Developer mode toggled:', enabled ? 'enabled' : 'disabled');
    } else {
        console.error('Developer mode input not found');
    }
}

// Show new API key modal if present
document.addEventListener('DOMContentLoaded', function() {
    @if(session('new_api_key'))
    const displayKeyModal = new bootstrap.Modal(document.getElementById('displayKeyModal'));
    displayKeyModal.show();
    @endif
});

// Delete account confirmation
document.addEventListener('DOMContentLoaded', function() {
    const confirmCheckbox = document.getElementById('confirmDelete');
    const deleteBtn = document.getElementById('deleteAccountBtn');
    
    if (confirmCheckbox && deleteBtn) {
        confirmCheckbox.addEventListener('change', function() {
            deleteBtn.disabled = !this.checked;
        });
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<style>
.avatar-upload {
    width: 120px;
    height: 120px;
}

.nav-pills .nav-link {
    border-radius: 8px;
    margin-bottom: 4px;
    color: var(--text);
    transition: all 0.2s ease;
    background: var(--card);
    border: 1px solid var(--border);
}

.nav-pills .nav-link:hover {
    background-color: color-mix(in srgb, var(--wa-green) 5%, transparent);
    color: var(--wa-green);
    border-color: var(--wa-green);
}

.nav-pills .nav-link.active {
    background-color: var(--wa-green);
    color: #062a1f;
    border: none;
}

.form-switch .form-check-input:checked {
    background-color: var(--wa-green);
    border-color: var(--wa-green);
}

.tab-content {
    min-height: 400px;
}

/* Ensure proper theming for all settings elements */
.card {
    background: var(--card);
    border-color: var(--border);
}

.card-header {
    background: var(--card) !important;
    border-color: var(--border) !important;
}

.card-body {
    background: var(--bg) !important;
}

.form-control, .form-select {
    background: var(--input-bg) !important;
    border-color: var(--input-border) !important;
    color: var(--text) !important;
}

.form-control:focus, .form-select:focus {
    border-color: var(--wa-green) !important;
    box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--wa-green) 25%, transparent) !important;
}

.text-muted {
    color: var(--wa-muted) !important;
}

.border-border {
    border-color: var(--border) !important;
}

.bg-card {
    background: var(--card) !important;
}

.bg-bg {
    background: var(--bg) !important;
}

.bg-input-bg {
    background: var(--input-bg) !important;
}

.border-input-border {
    border-color: var(--input-border) !important;
}

.text-text {
    color: var(--text) !important;
}

/* Quick Replies Styles */
.quick-reply-item {
    transition: all 0.2s ease;
    border-radius: 8px;
    margin-bottom: 4px;
}

.quick-reply-item:hover {
    background-color: color-mix(in srgb, var(--wa-green) 5%, transparent);
    transform: translateX(2px);
}

.quick-reply-item.sortable-ghost {
    opacity: 0.4;
    background-color: color-mix(in srgb, var(--wa-green) 10%, transparent);
}

.quick-reply-item.sortable-chosen {
    background-color: color-mix(in srgb, var(--wa-green) 8%, transparent);
    border-left: 3px solid var(--wa-green);
}

.drag-handle {
    cursor: grab;
    opacity: 0.5;
    transition: opacity 0.2s ease;
}

.drag-handle:hover {
    opacity: 1;
}

.quick-reply-message {
    line-height: 1.4;
    white-space: pre-wrap;
}

.empty-state-icon {
    opacity: 0.5;
}

.char-counter-warning {
    color: #dc3545;
    font-weight: 600;
}

/* Devices Styles */
.session-item {
    transition: all 0.2s ease;
    border-radius: 8px;
    margin-bottom: 4px;
}

.session-item:hover {
    background-color: color-mix(in srgb, var(--wa-green) 5%, transparent);
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
// Quick Replies JavaScript
$(document).ready(function() {
    let currentEditId = null;

    // Initialize Sortable for drag and drop reordering
    if (document.getElementById('quick-replies-list')) {
        new Sortable(document.getElementById('quick-replies-list'), {
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            animation: 150,
            onEnd: function(evt) {
                const order = [];
                $('#quick-replies-list .quick-reply-item').each(function(index) {
                    order.push($(this).data('reply-id'));
                });
                
                $.ajax({
                    url: '{{ route("settings.quick-replies.reorder") }}',
                    method: 'POST',
                    data: { order: order },
                    success: function(response) {
                        showQuickReplyAlert('success', 'Quick replies reordered successfully');
                    },
                    error: function() {
                        showQuickReplyAlert('error', 'Failed to reorder quick replies');
                        window.location.reload();
                    }
                });
            }
        });
    }

    // Character counters
    $('#quick-reply-title').on('input', function() {
        const maxLength = 100;
        const currentLength = $(this).val().length;
        const remaining = maxLength - currentLength;
        $('#quick-reply-title-char-count').text(`${remaining} characters left`)
            .toggleClass('char-counter-warning', remaining < 20);
    });

    $('#quick-reply-message').on('input', function() {
        const maxLength = 1000;
        const currentLength = $(this).val().length;
        const remaining = maxLength - currentLength;
        $('#quick-reply-message-char-count').text(`${remaining} characters left`)
            .toggleClass('char-counter-warning', remaining < 50);
    });

    // Add Quick Reply Modal
    $(document).on('click', '[data-bs-target="#addQuickReplyModal"]', function() {
        currentEditId = null;
        $('#quickReplyModalLabel').text('Add Quick Reply');
        $('#quickReplyForm').attr('action', '{{ route("settings.quick-replies.store") }}');
        $('#form-method').html('');
        $('#quick-reply-title, #quick-reply-message').val('');
        $('#submitQuickReplyBtn').html('<i class="bi bi-plus-lg me-1"></i> Save Quick Reply');
        $('#quick-reply-title-char-count').text('100 characters left').removeClass('char-counter-warning');
        $('#quick-reply-message-char-count').text('1000 characters left').removeClass('char-counter-warning');
        $('#addQuickReplyModal').modal('show');
    });

    // Edit Quick Reply
    $(document).on('click', '.edit-quick-reply-btn', function() {
        currentEditId = $(this).data('id');
        const title = $(this).data('title');
        const message = $(this).data('message');

        $('#quickReplyModalLabel').text('Edit Quick Reply');
        $('#quickReplyForm').attr('action', `/settings/quick-replies/${currentEditId}`);
        $('#form-method').html('@method("PUT")');
        $('#quick-reply-title').val(title);
        $('#quick-reply-message').val(message);
        $('#submitQuickReplyBtn').html('<i class="bi bi-check me-1"></i> Update Quick Reply');

        $('#quick-reply-title').trigger('input');
        $('#quick-reply-message').trigger('input');

        $('#addQuickReplyModal').modal('show');
    });

    // Quick Reply Form Submission
    $('#quickReplyForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const url = $(this).attr('action');
        const method = $('#form-method').find('input[name="_method"]').val() || 'POST';
        
        const submitBtn = $('#submitQuickReplyBtn');
        submitBtn.prop('disabled', true).html('<i class="bi bi-arrow-repeat spinner me-1"></i> Saving...');

        $.ajax({
            url: url,
            method: method,
            data: formData,
            success: function(response) {
                $('#addQuickReplyModal').modal('hide');
                showQuickReplyAlert('success', response.message || 'Quick reply saved successfully');
                setTimeout(() => window.location.reload(), 1000);
            },
            error: function(xhr) {
                const errors = xhr.responseJSON?.errors;
                if (errors) {
                    const firstError = Object.values(errors)[0][0];
                    showQuickReplyAlert('error', firstError);
                } else {
                    showQuickReplyAlert('error', 'Failed to save quick reply. Please try again.');
                }
            },
            complete: function() {
                submitBtn.prop('disabled', false).html(method === 'POST' ? 
                    '<i class="bi bi-plus-lg me-1"></i> Save Quick Reply' : 
                    '<i class="bi bi-check me-1"></i> Update Quick Reply');
            }
        });
    });

    // Delete Quick Reply
    $(document).on('click', '.delete-quick-reply-btn', function() {
        const replyId = $(this).data('id');
        const replyTitle = $(this).data('title');
        
        if (confirm(`Are you sure you want to delete "${replyTitle}"? This action cannot be undone.`)) {
            const $replyItem = $(`[data-reply-id="${replyId}"]`);
            
            $.ajax({
                url: `/settings/quick-replies/${replyId}`,
                method: 'DELETE',
                beforeSend: function() {
                    $replyItem.css('opacity', '0.5');
                },
                success: function(response) {
                    $replyItem.slideUp(300, function() {
                        $(this).remove();
                        if ($('.quick-reply-item').length === 0) {
                            location.reload();
                        }
                    });
                    showQuickReplyAlert('success', 'Quick reply deleted successfully!');
                },
                error: function() {
                    $replyItem.css('opacity', '1');
                    showQuickReplyAlert('error', 'Failed to delete quick reply. Please try again.');
                }
            });
        }
    });

    // Copy Message to Clipboard
    $(document).on('click', '.copy-message-btn', function() {
        const message = $(this).data('message');
        
        navigator.clipboard.writeText(message).then(function() {
            showQuickReplyAlert('success', 'Message copied to clipboard!');
        }).catch(function() {
            const textarea = document.createElement('textarea');
            textarea.value = message;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showQuickReplyAlert('success', 'Message copied to clipboard!');
        });
    });

    // Modal cleanup
    $('#addQuickReplyModal').on('hidden.bs.modal', function() {
        currentEditId = null;
        $('#quickReplyForm')[0].reset();
        $('#quick-reply-title-char-count').text('100 characters left').removeClass('char-counter-warning');
        $('#quick-reply-message-char-count').text('1000 characters left').removeClass('char-counter-warning');
    });

    function showQuickReplyAlert(type, message, duration = 5000) {
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';
        
        const icon = {
            'success': 'bi-check-circle-fill',
            'error': 'bi-exclamation-triangle-fill',
            'warning': 'bi-exclamation-circle-fill',
            'info': 'bi-info-circle-fill'
        }[type] || 'bi-info-circle-fill';
        
        const alertId = 'qr-alert-' + Date.now();
        const alertHtml = `
            <div id="${alertId}" class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="bi ${icon} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        $('#quick-replies-ajax-alerts-container').html(alertHtml);
        
        setTimeout(() => {
            $(`#${alertId}`).alert('close');
        }, duration);
    }
});

// Devices JavaScript
$(document).ready(function() {
    // Log out a specific session
    $(document).on('click', '.logout-session-btn', function() {
        const sessionId = $(this).data('session-id');
        const platform = $(this).data('platform');
        const browser = $(this).data('browser');
        
        if (confirm(`Are you sure you want to log out from ${platform} • ${browser}? This will terminate that session immediately.`)) {
            const $sessionItem = $(`[data-session-id="${sessionId}"]`);
            
            $.ajax({
                url: `/settings/devices/${sessionId}`,
                method: 'DELETE',
                beforeSend: function() {
                    $sessionItem.css('opacity', '0.5');
                },
                success: function(response) {
                    if (response.redirect) {
                        window.location.href = response.redirect;
                    } else {
                        $sessionItem.slideUp(300, function() {
                            $(this).remove();
                            if ($('.session-item').length === 0) {
                                location.reload();
                            }
                        });
                        showDeviceAlert('success', response.message);
                    }
                },
                error: function(xhr) {
                    $sessionItem.css('opacity', '1');
                    const error = xhr.responseJSON?.message || 'Failed to log out session';
                    showDeviceAlert('error', error);
                }
            });
        }
    });

    // Log out all other sessions
    $('#logoutAllOtherBtn').on('click', function() {
        if (confirm('Are you sure you want to log out all other devices? This will terminate all sessions except this one.')) {
            const $btn = $(this);
            const originalText = $btn.html();
            
            $btn.prop('disabled', true).html('<i class="bi bi-arrow-repeat spinner me-1"></i> Logging out...');

            $.ajax({
                url: '{{ route("settings.devices.logout-all-other") }}',
                method: 'DELETE',
                success: function(response) {
                    $('#sessions-list').slideUp(300, function() {
                        $(this).remove();
                        location.reload();
                    });
                    showDeviceAlert('success', response.message);
                },
                error: function(xhr) {
                    const error = xhr.responseJSON?.message || 'Failed to log out sessions';
                    showDeviceAlert('error', error);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        }
    });

    function showDeviceAlert(type, message, duration = 5000) {
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';
        
        const icon = {
            'success': 'bi-check-circle-fill',
            'error': 'bi-exclamation-triangle-fill',
            'warning': 'bi-exclamation-circle-fill',
            'info': 'bi-info-circle-fill'
        }[type] || 'bi-info-circle-fill';
        
        const alertId = 'dev-alert-' + Date.now();
        const alertHtml = `
            <div id="${alertId}" class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="bi ${icon} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        $('#devices-ajax-alerts-container').html(alertHtml);
        
        setTimeout(() => {
            $(`#${alertId}`).alert('close');
        }, duration);
    }

    // Notification Settings Handler
    (function() {
        const notificationForm = document.querySelector('#notifications form');
        const browserNotificationCheckbox = document.getElementById('browser_notifications');
        const browserNotificationStatus = document.getElementById('browser_notification_status');
        const saveButton = document.getElementById('save-notifications-btn');
        
        // Check browser notification permission status on load
        function updateBrowserNotificationStatus() {
            if (!('Notification' in window)) {
                if (browserNotificationStatus) {
                    browserNotificationStatus.textContent = 'Browser notifications are not supported in this browser.';
                    browserNotificationStatus.className = 'form-text text-danger mt-1';
                }
                if (browserNotificationCheckbox) {
                    browserNotificationCheckbox.disabled = true;
                }
                return;
            }
            
            const permission = Notification.permission;
            if (browserNotificationStatus) {
                if (permission === 'granted') {
                    browserNotificationStatus.textContent = '✓ Browser notifications are enabled';
                    browserNotificationStatus.className = 'form-text text-success mt-1';
                } else if (permission === 'denied') {
                    browserNotificationStatus.textContent = '⚠ Browser notifications are blocked. Please enable them in your browser settings.';
                    browserNotificationStatus.className = 'form-text text-warning mt-1';
                } else {
                    browserNotificationStatus.textContent = 'Click the checkbox to enable browser notifications';
                    browserNotificationStatus.className = 'form-text text-muted mt-1';
                }
            }
        }
        
        // Update status on page load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', updateBrowserNotificationStatus);
        } else {
            updateBrowserNotificationStatus();
        }
        
        // Handle browser notification checkbox change
        if (browserNotificationCheckbox) {
            browserNotificationCheckbox.addEventListener('change', async function(e) {
                if (!('Notification' in window)) {
                    e.target.checked = false;
                    alert('Browser notifications are not supported in this browser.');
                    return;
                }
                
                if (e.target.checked) {
                    // Request permission if not already granted
                    if (Notification.permission === 'default') {
                        try {
                            const permission = await Notification.requestPermission();
                            if (permission === 'granted') {
                                updateBrowserNotificationStatus();
                                // Show a test notification
                                try {
                                    new Notification('GekyChat', {
                                        body: 'Browser notifications are now enabled!',
                                        icon: '/icons/icon-192x192.png'
                                    });
                                } catch (err) {
                                    console.log('Could not show test notification:', err);
                                }
                            } else if (permission === 'denied') {
                                e.target.checked = false;
                                updateBrowserNotificationStatus();
                                alert('Notification permission was denied. Please enable it in your browser settings.');
                            }
                        } catch (error) {
                            console.error('Error requesting notification permission:', error);
                            e.target.checked = false;
                            alert('Failed to request notification permission. Please try again.');
                        }
                    } else if (Notification.permission === 'denied') {
                        e.target.checked = false;
                        updateBrowserNotificationStatus();
                        alert('Notification permission is denied. Please enable it in your browser settings.');
                    } else {
                        updateBrowserNotificationStatus();
                    }
                } else {
                    updateBrowserNotificationStatus();
                }
            });
        }
        
        // Handle form submission via AJAX
        if (notificationForm && saveButton) {
            notificationForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const originalButtonText = saveButton.textContent;
                
                // Disable button and show loading state
                saveButton.disabled = true;
                saveButton.textContent = 'Saving...';
                
                try {
                    const response = await fetch(this.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    });
                    
                    let result;
                    try {
                        result = await response.json();
                    } catch (jsonError) {
                        // If response is not JSON, check if it's a redirect or HTML
                        if (response.ok) {
                            result = { success: true, message: 'Settings updated successfully' };
                        } else {
                            result = { success: false, message: 'Failed to save settings' };
                        }
                    }
                    
                    if (response.ok && result.success !== false) {
                        // Show success message
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-success alert-dismissible fade show';
                        alertDiv.innerHTML = `
                            <i class="bi bi-check-circle-fill me-2"></i>
                            ${result.message || 'Notification settings saved successfully!'}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        `;
                        notificationForm.insertBefore(alertDiv, notificationForm.firstChild);
                        
                        // Remove alert after 5 seconds
                        setTimeout(() => {
                            alertDiv.remove();
                        }, 5000);
                        
                        // Update browser notification status
                        updateBrowserNotificationStatus();
                    } else {
                        // Show error message
                        const errorMsg = result.message || result.error || 'Failed to save notification settings';
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                        alertDiv.innerHTML = `
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            ${errorMsg}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        `;
                        notificationForm.insertBefore(alertDiv, notificationForm.firstChild);
                        
                        // Remove alert after 5 seconds
                        setTimeout(() => {
                            alertDiv.remove();
                        }, 5000);
                    }
                } catch (error) {
                    console.error('Error saving notification settings:', error);
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Failed to save notification settings. Please try again.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    notificationForm.insertBefore(alertDiv, notificationForm.firstChild);
                    
                    setTimeout(() => {
                        alertDiv.remove();
                    }, 5000);
                } finally {
                    // Re-enable button
                    saveButton.disabled = false;
                    saveButton.textContent = originalButtonText;
                }
            });
        }
    })();

    // Update session activity periodically (every 5 minutes)
    setInterval(() => {
        $.ajax({
            url: '{{ route("settings.devices.update-activity") }}',
            method: 'POST',
            error: function() {
                console.log('Failed to update session activity');
            }
        });
    }, 300000); // 5 minutes
});
</script>
@endpush