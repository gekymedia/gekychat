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
                        <button class="nav-link" id="notifications-tab" data-bs-toggle="pill" 
                                data-bs-target="#notifications" type="button" role="tab" 
                                aria-controls="notifications" aria-selected="false">
                            <i class="bi bi-bell me-2"></i>Notifications
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

                        {{-- Notifications Tab --}}
                        <div class="tab-pane fade" id="notifications" role="tabpanel" 
                             aria-labelledby="notifications-tab">
                            <form action="{{ route('settings.update') }}" method="POST">
                                @method('PUT')
                                @csrf
                                <div class="mb-4">
                                    <h6 class="fw-semibold mb-3 text-text">Push Notifications</h6>
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

                                <button type="submit" class="btn btn-wa">Save Notification Settings</button>
                            </form>
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
                            <div class="mb-4">
                                <h6 class="fw-semibold mb-3 text-text">Security</h6>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="two_factor_enabled" 
                                           name="account[two_factor_enabled]" value="1"
                                           {{ ($settings['account']['two_factor_enabled'] ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label text-text" for="two_factor_enabled">
                                        Two-factor authentication
                                    </label>
                                </div>
                            </div>

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
                            </div>

                            <div class="mb-4">
                                <h6 class="fw-semibold mb-3 text-text">Change Password</h6>
                                <form action="{{ route('settings.password') }}" method="POST">
                                    @method('PUT')
                                    @csrf
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label text-text">Current Password</label>
                                        <input type="password" class="form-control bg-input-bg border-input-border text-text" id="current_password" 
                                               name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="password" class="form-label text-text">New Password</label>
                                        <input type="password" class="form-control bg-input-bg border-input-border text-text" id="password" 
                                               name="password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="password_confirmation" class="form-label text-text">Confirm New Password</label>
                                        <input type="password" class="form-control bg-input-bg border-input-border text-text" id="password_confirmation" 
                                               name="password_confirmation" required>
                                    </div>
                                    <button type="submit" class="btn btn-wa">Change Password</button>
                                </form>
                            </div>

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
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
</style>
@endpush