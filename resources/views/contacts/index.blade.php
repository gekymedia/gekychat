{{-- resources/views/contacts/index.blade.php --}}
@extends('layouts.app')

@section('title', 'My Contacts - ' . config('app.name', 'GekyChat'))

@section('content')
<div class="container-fluid h-100 d-flex flex-column">
    <div class="card border-0 shadow-sm h-100 d-flex flex-column">
        <div class="card-header bg-card border-bottom border-border py-3 flex-shrink-0">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h1 class="h4 mb-0 fw-bold text-text">My Contacts</h1>
                    <p class="text-muted mb-0">Manage your contact list</p>
                </div>
                <div class="d-flex gap-2">
    {{-- Only show Sync button if connected, otherwise no button in header when disconnected --}}
    @if(auth()->user()->hasGoogleAccess())
        <button class="btn btn-outline-primary btn-sm me-2" id="googleSyncBtn" title="Sync Google Contacts">
            <i class="bi bi-google me-1"></i> Sync Google
        </button>
    @endif
    <button class="btn btn-wa btn-sm" data-bs-toggle="modal" data-bs-target="#addContactModal">
        <i class="bi bi-plus-lg me-1"></i> Add Contact
    </button>
</div>
            </div>
        </div>

        <div class="card-body bg-bg d-flex flex-column flex-grow-1 overflow-hidden p-0">
            <div class="px-3 pt-3 flex-shrink-0">
            {{-- Google Contacts Sync Status --}}
           {{-- Google Contacts Sync Status --}}
<div id="google-sync-status" class="mb-4">
    @if(auth()->user()->hasGoogleAccess())
        {{-- Connected State --}}
        <div class="card bg-card border-border">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 rounded-circle p-2">
                            <i class="bi bi-google text-primary"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-text">Google Contacts Connected</h6>
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <small class="text-muted">
                                    <i class="bi bi-envelope me-1"></i>
                                    Connected as: <strong>{{ auth()->user()->google_email ?? 'Google Account' }}</strong>
                                </small>
                                <small class="text-muted">
                                    <i class="bi bi-arrow-repeat me-1"></i>
                                    Last sync: 
                                    <span id="last-sync-time">
                                        {{ auth()->user()->last_google_sync_at ? auth()->user()->last_google_sync_at->diffForHumans() : 'Never' }}
                                    </span>
                                </small>
                                <small class="text-muted">
                                    <i class="bi bi-people me-1"></i>
                                    <span id="google-contacts-count">{{ auth()->user()->contacts()->where('source', 'google_sync')->count() }}</span> contacts synced
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary btn-sm" id="manualSyncBtn">
                            <i class="bi bi-arrow-repeat me-1"></i> Sync Now
                        </button>
                        <button class="btn btn-outline-danger btn-sm" id="disconnectGoogleBtn">
                            <i class="bi bi-x-circle me-1"></i> Disconnect
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- Not Connected State --}}
        <div class="card bg-card border-border">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-light rounded-circle p-2">
                            <i class="bi bi-google text-muted"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-text">Sync with Google Contacts</h6>
                            <p class="text-muted mb-0">Connect your Google account to automatically sync contacts</p>
                        </div>
                    </div>
                    <a href="{{ route('google.redirect') }}" class="btn btn-primary btn-sm" id="connectGoogleBtn">
                        <i class="bi bi-google me-1"></i> Connect Google Account
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>

            {{-- Status Messages --}}
            <div id="ajax-alerts-container"></div>

            @if (session('status'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            {{-- Bulk Actions --}}
            <div id="bulkActions" class="d-none mt-3 mb-3 p-3 rounded border border-border">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAllContacts">
                            <label class="form-check-label fw-semibold text-text" for="selectAllContacts">
                                Select all
                            </label>
                        </div>
                        <span class="text-muted" id="selectedCount">0 contacts selected</span>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-warning btn-sm" id="bulkFavoriteBtn">
                            <i class="bi bi-star me-1"></i>Add to Favorites
                        </button>
                        <button class="btn btn-outline-danger btn-sm" id="bulkDeleteBtn">
                            <i class="bi bi-trash me-1"></i>Delete Selected
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" id="clearSelectionBtn">
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            {{-- Contacts Filter & Search --}}
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-input-bg border-input-border border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" id="contacts-search" class="form-control bg-input-bg border-input-border border-start-0 text-text" 
                               placeholder="Search contacts by name or phone...">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex gap-2 justify-content-md-end align-items-center">
                        <small class="text-muted d-none d-md-block">
                            Showing <span id="visibleCount">{{ $contacts->count() }}</span> contacts
                        </small>
                        <div class="btn-group btn-group-sm" role="group">
                            <input type="radio" class="btn-check" name="contact-filter" id="filter-all" checked>
                            <label class="btn btn-outline-secondary border-input-border text-text" for="filter-all">All</label>

                            <input type="radio" class="btn-check" name="contact-filter" id="filter-registered">
                            <label class="btn btn-outline-secondary border-input-border text-text" for="filter-registered">On GekyChat</label>

                            <input type="radio" class="btn-check" name="contact-filter" id="filter-favorites">
                            <label class="btn btn-outline-secondary border-input-border text-text" for="filter-favorites">Favorites</label>

                            <input type="radio" class="btn-check" name="contact-filter" id="filter-google">
                            <label class="btn btn-outline-secondary border-input-border text-text" for="filter-google">Google</label>

                            <input type="radio" class="btn-check" name="contact-filter" id="filter-phone">
                            <label class="btn btn-outline-secondary border-input-border text-text" for="filter-phone">Phone</label>

                            <input type="radio" class="btn-check" name="contact-filter" id="filter-manual">
                            <label class="btn btn-outline-secondary border-input-border text-text" for="filter-manual">Manual</label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Contacts List --}}
            <div id="contacts-container" class="flex-grow-1 overflow-auto" style="max-height: calc(100vh - 400px);">
                @if($contacts->count() > 0)
                    <div class="list-group list-group-flush" id="contacts-list">
                        @foreach($contacts as $contact)
                            <div class="list-group-item contact-item d-flex align-items-center px-0 py-3 bg-card border-border" 
                                 data-contact-id="{{ $contact->id }}"
                                 data-name="{{ strtolower($contact->display_name ?? '') }}"
                                 data-phone="{{ strtolower($contact->phone) }}"
                                 data-registered="{{ $contact->contact_user_id ? 'true' : 'false' }}"
                                 data-favorite="{{ $contact->is_favorite ? 'true' : 'false' }}"
                                 data-source="{{ $contact->source ?? 'manual' }}">
                                
                                {{-- Checkbox for bulk selection --}}
                                <div class="me-3">
                                    <input type="checkbox" class="form-check-input contact-checkbox" value="{{ $contact->id }}">
                                </div>

                                {{-- Avatar --}}
                                <div class="avatar me-3" style="width: 40px; height: 40px;">
                                    @if($contact->contactUser && $contact->contactUser->avatar_path)
                                        <img src="{{ Storage::url($contact->contactUser->avatar_path) }}" 
                                             class="avatar-img" 
                                             alt="{{ $contact->display_name }}"
                                             style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%;"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    @endif
                                    <div class="avatar-placeholder avatar-md" style="display: {{ $contact->contactUser && $contact->contactUser->avatar_path ? 'none' : 'flex' }};">
                                        {{ $contact->initial }}
                                    </div>
                                </div>

                                {{-- Contact Info --}}
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <h6 class="mb-0 fw-semibold text-text">
                                            {{ $contact->display_name ?: $contact->phone }}
                                            @if($contact->source === 'google_sync')
                                                <span class="badge google-contact-badge small ms-2">
                                                    <i class="bi bi-google me-1"></i>Google
                                                </span>
                                            @elseif($contact->source === 'phone')
                                                <span class="badge phone-contact-badge small ms-2">
                                                    <i class="bi bi-phone me-1"></i>Phone
                                                </span>
                                            @endif
                                        </h6>
                                        <div class="d-flex align-items-center gap-2">
                                            @if($contact->is_favorite)
                                                <i class="bi bi-star-fill text-warning" title="Favorite"></i>
                                            @endif
                                            @if($contact->contact_user_id)
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                                    On GekyChat
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        <span class="text-muted small">{{ $contact->phone }}</span>
                                        @if($contact->note)
                                            <span class="text-muted small" title="{{ $contact->note }}">
                                                <i class="bi bi-sticky me-1"></i>Has note
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="dropdown ms-3">
                                    <button class="btn btn-sm btn-outline-secondary border-0 text-text" type="button" 
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end bg-card border-border">
                                        @if($contact->contact_user_id)
                                            <li>
                                                <a class="dropdown-item text-text" href="{{ route('chat.start') }}?user_id={{ $contact->contact_user_id }}">
                                                    <i class="bi bi-chat-dots me-2"></i>Send Message
                                                </a>
                                            </li>
                                        @else
                                            <li>
                                                <button class="dropdown-item text-text invite-contact-btn" 
                                                        data-phone="{{ $contact->phone }}"
                                                        data-name="{{ $contact->display_name }}">
                                                    <i class="bi bi-share me-2"></i>Invite to GekyChat
                                                </button>
                                            </li>
                                        @endif
                                        <li>
                                            <button class="dropdown-item text-text favorite-btn {{ $contact->is_favorite ? 'active' : '' }}" 
                                                    data-id="{{ $contact->id }}">
                                                <i class="bi bi-star{{ $contact->is_favorite ? '-fill' : '' }} me-2 {{ $contact->is_favorite ? 'text-warning' : '' }}"></i>
                                                {{ $contact->is_favorite ? 'Remove from' : 'Add to' }} Favorites
                                            </button>
                                        </li>
                                        @if($contact->source === 'google_sync')
                                        <li>
                                            <button class="dropdown-item text-text google-contact-info-btn" 
                                                    data-phone="{{ $contact->phone }}"
                                                    data-name="{{ $contact->display_name }}">
                                                <i class="bi bi-info-circle me-2"></i>Sync Info
                                            </button>
                                        </li>
                                        @endif
                                        {{-- In the dropdown menu, add block/unblock options --}}
@if($contact->contact_user_id && $contact->contact_user_id != auth()->id())
    <li><hr class="dropdown-divider border-border"></li>
    @if(auth()->user()->hasBlocked($contact->contact_user_id))
        <li>
            <button class="dropdown-item text-success unblock-user-btn" 
                    data-user-id="{{ $contact->contact_user_id }}"
                    data-name="{{ $contact->display_name }}">
                <i class="bi bi-unlock me-2"></i>Unblock User
            </button>
        </li>
    @else
        <li>
            <button class="dropdown-item text-warning block-user-btn" 
                    data-user-id="{{ $contact->contact_user_id }}"
                    data-name="{{ $contact->display_name }}">
                <i class="bi bi-slash-circle me-2"></i>Block User
            </button>
        </li>
    @endif
@endif
                                        <li><hr class="dropdown-divider border-border"></li>
                                        <li>
                                            <button class="dropdown-item text-danger delete-contact-btn" 
                                                    data-id="{{ $contact->id }}" 
                                                    data-name="{{ $contact->display_name ?: $contact->phone }}">
                                                <i class="bi bi-trash me-2"></i>Delete Contact
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    {{-- Empty State --}}
                    <div class="text-center py-5" id="empty-state">
                        <div class="empty-state-icon mb-4">
                            <i class="bi bi-person-plus display-1 text-muted"></i>
                        </div>
                        <h4 class="text-muted mb-3">No contacts yet</h4>
                        <p class="text-muted mb-4">Add your first contact to start chatting</p>
                        <button class="btn btn-wa" data-bs-toggle="modal" data-bs-target="#addContactModal">
                            <i class="bi bi-plus-lg me-2"></i>Add Your First Contact
                        </button>
                    </div>
                @endif
            </div>

            {{-- No Results State (hidden by default) --}}
            <div id="no-results-state" class="text-center py-5 d-none">
                <div class="empty-state-icon mb-4">
                    <i class="bi bi-search display-1 text-muted"></i>
                </div>
                <h4 class="text-muted mb-3">No contacts found</h4>
                <p class="text-muted mb-4">Try adjusting your search or filter</p>
            </div>
        </div>
    </div>
</div>
{{-- Block User Modal --}}
<div class="modal fade" id="blockUserModal" tabindex="-1" aria-labelledby="blockUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-card border-border">
            <div class="modal-header border-border">
                <h5 class="modal-title fw-bold text-text" id="blockUserModalLabel">Block User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="blockUserForm">
                @csrf
                <div class="modal-body text-text">
                    <p>You are about to block <strong id="blockUserName"></strong>.</p>
                    <div class="mb-3">
                        <label for="blockReason" class="form-label text-text">Reason (Optional)</label>
                        <textarea class="form-control bg-input-bg border-input-border text-text" id="blockReason" name="reason" rows="3" placeholder="Why are you blocking this user?"></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Blocked users cannot send you messages or see your online status.
                    </div>
                </div>
                <div class="modal-footer border-border">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="confirmBlockBtn">
                        <i class="bi bi-slash-circle me-1"></i> Block User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
{{-- Add Contact Modal --}}
<div class="modal fade" id="addContactModal" tabindex="-1" aria-labelledby="addContactModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-card border-border">
            <div class="modal-header border-border">
                <h5 class="modal-title fw-bold text-text" id="addContactModalLabel">Add New Contact</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addContactForm" action="{{ route('contacts.store') }}" method="POST">
                @csrf
                <div class="modal-body text-text">
                    <div class="mb-3">
                        <label for="display_name" class="form-label text-text">Display Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-input-bg border-input-border text-text" id="display_name" name="display_name" 
                               placeholder="Enter contact name" required maxlength="255">
                        <div class="form-text text-end text-muted">
                            <span id="name-char-count">255 characters left</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label text-text">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control bg-input-bg border-input-border text-text" id="phone" name="phone" 
                               placeholder="+233XXXXXXXXX" required>
                        <div class="form-text text-muted">Enter phone number with country code</div>
                    </div>
                    <div class="mb-3">
                        <label for="note" class="form-label text-text">Note (Optional)</label>
                        <textarea class="form-control bg-input-bg border-input-border text-text" id="note" name="note" rows="2" 
                                  placeholder="Add a note about this contact..." maxlength="500"></textarea>
                        <div class="form-text text-end text-muted">
                            <span id="note-char-count">500 characters left</span>
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_favorite" name="is_favorite" value="1">
                        <label class="form-check-label text-text" for="is_favorite">
                            Add to favorites
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-border">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-wa" id="submitContactBtn">
                        <i class="bi bi-plus-lg me-1"></i> Save Contact
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Invite Modal --}}
<div class="modal fade" id="inviteModal" tabindex="-1" aria-labelledby="inviteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-card border-border">
            <div class="modal-header border-border">
                <h5 class="modal-title fw-bold text-text" id="inviteModalLabel">Invite to GekyChat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-text">
                <p class="mb-2">Invite <strong id="inviteContactName"></strong> to join <strong>{{ config('app.name', 'GekyChat') }}</strong></p>
                <div class="small text-muted mb-3" id="invitePhoneHint"></div>

                <div class="d-grid gap-2">
                    <a id="inviteSmsBtn" class="btn btn-wa btn-sm" target="_blank" rel="noopener">
                        <i class="bi bi-chat-dots me-1"></i> Send SMS Invite
                    </a>
                    <button id="inviteShareBtn" class="btn btn-outline-wa btn-sm">
                        <i class="bi bi-share me-1"></i> Share Invite
                    </button>
                    <div class="input-group">
                        <input id="inviteLinkInput" class="form-control form-control-sm bg-input-bg border-input-border text-text" readonly>
                        <button id="inviteCopyBtn" class="btn btn-outline-secondary btn-sm" type="button">
                            <i class="bi bi-clipboard me-1"></i> Copy
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-border">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Google Sync Progress Modal --}}
<div class="modal fade" id="syncProgressModal" tabindex="-1" aria-labelledby="syncProgressModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content bg-card border-border">
            <div class="modal-body text-center py-4">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Syncing...</span>
                </div>
                <h6 class="text-text mb-2">Syncing Google Contacts</h6>
                <p class="text-muted small mb-0">This may take a few moments...</p>
            </div>
        </div>
    </div>
</div>

{{-- Loading Spinner --}}
<div id="loadingSpinner" class="d-none text-center py-4">
    <div class="spinner-border text-wa" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="text-muted mt-2">Loading contacts...</p>
</div>
@endsection

@push('styles')
<style>
/* Google-specific styles */
.google-contact-badge {
    background: color-mix(in srgb, #4285f4 10%, transparent);
    color: #4285f4;
    border: 1px solid color-mix(in srgb, #4285f4 30%, transparent);
    font-size: 0.7rem;
    font-weight: 500;
}

/* Phone contact badge styles */
.phone-contact-badge {
    background: color-mix(in srgb, #008069 10%, transparent);
    color: #008069;
    border: 1px solid color-mix(in srgb, #008069 30%, transparent);
    font-size: 0.7rem;
    font-weight: 500;
}

/* Your existing styles remain the same */
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

.btn-outline-secondary {
    border-color: var(--border);
    color: var(--text);
}

.btn-outline-secondary:hover {
    background: var(--border);
    border-color: var(--border);
    color: var(--text);
}

.dropdown-menu {
    background: var(--card);
    border-color: var(--border);
}

.dropdown-item {
    color: var(--text);
}

.dropdown-item:hover {
    background: color-mix(in srgb, var(--wa-green) 10%, transparent);
    color: var(--text);
}

.modal-content {
    background: var(--card);
    border-color: var(--border);
}

.modal-header, .modal-footer {
    border-color: var(--border);
}

.alert {
    border: 1px solid var(--border);
}

.alert-success {
    background: color-mix(in srgb, var(--wa-green) 10%, var(--card));
    color: var(--text);
}

.alert-danger {
    background: color-mix(in srgb, #dc3545 10%, var(--card));
    color: var(--text);
}

.alert-warning {
    background: color-mix(in srgb, #ffc107 10%, var(--card));
    color: var(--text);
}

/* Additional contacts-specific styles */
.avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* Avatar placeholder styles now use global .avatar-placeholder class from app.css */

.contact-item {
    transition: all 0.2s ease;
    border-radius: 8px;
    margin-bottom: 4px;
}

.contact-item:hover {
    background-color: color-mix(in srgb, var(--wa-green) 5%, transparent);
    transform: translateX(2px);
}

.contact-item.selected {
    background-color: color-mix(in srgb, var(--wa-green) 10%, transparent);
    border-left: 3px solid var(--wa-green);
}

.empty-state-icon {
    opacity: 0.5;
}

/* bg-avatar removed - use .avatar-placeholder class from app.css instead */

.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

#bulkActions {
    animation: slideDown 0.2s ease-out;
    background: var(--card) !important;
    border-color: var(--border) !important;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.contact-checkbox {
    transform: scale(1.1);
}

/* Button styles */
.btn-wa {
    background-color: var(--wa-green);
    border-color: var(--wa-green);
    color: #062a1f;
}

.btn-wa:hover {
    background-color: color-mix(in srgb, var(--wa-green) 80%, black);
    border-color: color-mix(in srgb, var(--wa-green) 80%, black);
    color: #062a1f;
}

.btn-outline-wa {
    color: var(--wa-green);
    border-color: var(--wa-green);
}

.btn-outline-wa:hover {
    background-color: var(--wa-green);
    color: #062a1f;
}

/* Badge styles */
.badge {
    background: var(--card);
    border: 1px solid var(--border);
}

.bg-success {
    background: color-mix(in srgb, var(--wa-green) 10%, transparent) !important;
    color: var(--wa-green) !important;
    border-color: var(--wa-green) !important;
}

/* Pagination styles */
.pagination .page-link {
    background: var(--card);
    border-color: var(--border);
    color: var(--text);
}

.pagination .page-link:hover {
    background: var(--border);
    border-color: var(--wa-green);
}

.pagination .page-item.active .page-link {
    background: var(--wa-green);
    border-color: var(--wa-green);
    color: #062a1f;
}

/* Form check styles */
.form-check-input {
    background-color: var(--input-bg);
    border-color: var(--border);
}

.form-check-input:checked {
    background-color: var(--wa-green);
    border-color: var(--wa-green);
}

/* Input group styles */
.input-group-text {
    background: var(--input-bg);
    border-color: var(--input-border);
    color: var(--wa-muted);
}

/* Button group styles */
.btn-group .btn-check:checked + .btn {
    background: var(--wa-green);
    border-color: var(--wa-green);
    color: #062a1f;
}

/* Loading spinner */
.spinner-border.text-wa {
    color: var(--wa-green) !important;
}
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // CSRF token setup
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // ============================================
    // SEARCH AND FILTER FUNCTIONALITY
    // ============================================
    
    // Debounce function for search input
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Main filter function - filters contacts based on search and active filter tab
    function filterContacts() {
        const searchTerm = $('#contacts-search').val().toLowerCase().trim();
        const activeFilter = $('input[name="contact-filter"]:checked').attr('id');
        
        let visibleCount = 0;
        
        $('.contact-item').each(function() {
            const $item = $(this);
            const name = ($item.data('name') || '').toLowerCase();
            const phone = ($item.data('phone') || '').toLowerCase();
            // Get registered status - handle both string and boolean from jQuery .data()
            const registeredValue = $item.data('registered');
            const registered = registeredValue === true || registeredValue === 'true';
            const favoriteValue = $item.data('favorite');
            const favorite = favoriteValue === true || favoriteValue === 'true';
            const source = $item.data('source') || 'manual';
            
            // Check if matches search term
            let matchesSearch = true;
            if (searchTerm) {
                matchesSearch = name.includes(searchTerm) || phone.includes(searchTerm);
            }
            
            // Check if matches active filter
            let matchesFilter = true;
            switch(activeFilter) {
                case 'filter-registered':
                    // Show all contacts that are registered on GekyChat (have contact_user_id)
                    // regardless of source type (google, manual, etc.)
                    matchesFilter = registered;
                    break;
                case 'filter-favorites':
                    matchesFilter = favorite;
                    break;
                case 'filter-google':
                    matchesFilter = source === 'google_sync';
                    break;
                case 'filter-phone':
                    matchesFilter = source === 'phone' || source === 'device';
                    break;
                case 'filter-manual':
                    matchesFilter = (source === 'manual' || source === '') && source !== 'phone' && source !== 'device';
                    break;
                case 'filter-all':
                default:
                    matchesFilter = true;
                    break;
            }
            
            // Show/hide based on both conditions
            const isVisible = matchesSearch && matchesFilter;
            if (isVisible) {
                $item.show();
                visibleCount++;
            } else {
                $item.hide();
            }
        });
        
        // Update visible count
        updateVisibleCount(visibleCount);
        
        // Show/hide empty states
        checkNoResultsState(visibleCount);
    }

    // Update visible count display
    function updateVisibleCount(count) {
        $('#visibleCount').text(count);
    }

    // Show/hide empty state and no results state
    function checkNoResultsState(visibleCount) {
        const totalContacts = $('.contact-item').length;
        
        if (visibleCount === 0 && totalContacts > 0) {
            // No results found but contacts exist
            $('#no-results-state').removeClass('d-none');
            $('#contacts-list').addClass('d-none');
            $('#empty-state').addClass('d-none');
        } else if (visibleCount === 0 && totalContacts === 0) {
            // No contacts at all
            $('#empty-state').removeClass('d-none');
            $('#no-results-state').addClass('d-none');
            $('#contacts-list').addClass('d-none');
        } else {
            // Show contacts list
            $('#no-results-state').addClass('d-none');
            $('#empty-state').addClass('d-none');
            $('#contacts-list').removeClass('d-none');
        }
    }

    // ============================================
    // EVENT HANDLERS FOR SEARCH AND FILTER
    // ============================================
    
    // Search input - filter on typing (debounced)
    $('#contacts-search').on('input', debounce(function() {
        filterContacts();
    }, 300));

    // Filter tabs - filter on change
    $('input[name="contact-filter"]').on('change', function() {
        filterContacts();
    });

    // Initialize filter on page load
    filterContacts();

    // ============================================
    // GOOGLE CONTACTS FUNCTIONS
    // ============================================

    // ============================================
    // ALERT FUNCTION
    // ============================================
    
    function showAlert(type, message, duration = 5000) {
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
        
        const alertId = 'alert-' + Date.now();
        const alertHtml = `
            <div id="${alertId}" class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="bi ${icon} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        $('#ajax-alerts-container').html(alertHtml);
        
        // Auto-dismiss after duration
        setTimeout(() => {
            $(`#${alertId}`).alert('close');
        }, duration);
    }

    // Add Contact Form Submission
    $('#addContactForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const submitBtn = $('#submitContactBtn');
        
        submitBtn.prop('disabled', true).html('<i class="bi bi-arrow-repeat spinner me-1"></i> Saving...');

        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: formData,
            success: function(response) {
                // Handle both response formats
                const contactData = response.data || response;
                
                if (!contactData || !contactData.id) {
                    console.error('Invalid response data:', response);
                    showAlert('error', 'Contact added but failed to display. Please refresh the page.');
                    $('#addContactModal').modal('hide');
                    resetForm('#addContactForm');
                    setTimeout(() => location.reload(), 1000);
                    return;
                }
                
                $('#addContactModal').modal('hide');
                showAlert('success', response.message || 'Contact added successfully!');
                resetForm('#addContactForm');
                
                // Add new contact to the list without refresh
                addContactToDOM(contactData);
                updateContactCount();
                filterContacts(); // Re-apply filters after adding
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                let errorMessage = 'Failed to add contact. Please try again.';
                
                if (response) {
                    // Check for validation errors
                    if (response.errors) {
                        const firstError = Object.values(response.errors)[0];
                        errorMessage = Array.isArray(firstError) ? firstError[0] : firstError;
                    } 
                    // Check for custom error message
                    else if (response.message) {
                        errorMessage = response.message;
                    }
                }
                
                showAlert('error', errorMessage);
                
                // Re-enable submit button
                submitBtn.prop('disabled', false).html('<i class="bi bi-plus-lg me-1"></i> Save Contact');
            },
            complete: function() {
                submitBtn.prop('disabled', false).html('<i class="bi bi-plus-lg me-1"></i> Save Contact');
            }
        });
    });

    // Delete Contact
    $(document).on('click', '.delete-contact-btn', function() {
        const contactId = $(this).data('id');
        const contactName = $(this).data('name');
        
        if (confirm(`Are you sure you want to delete "${contactName}"? This action cannot be undone.`)) {
            const $contactItem = $(`[data-contact-id="${contactId}"]`);
            
            $.ajax({
                url: `/contacts/${contactId}`,
                method: 'DELETE',
                beforeSend: function() {
                    $contactItem.css('opacity', '0.5');
                },
                success: function(response) {
                    $contactItem.slideUp(300, function() {
                        $(this).remove();
                        updateContactCount();
                        checkEmptyState();
                        filterContacts(); // Re-apply filters after deletion
                    });
                    showAlert('success', 'Contact deleted successfully!');
                },
                error: function(xhr) {
                    $contactItem.css('opacity', '1');
                    showAlert('error', 'Failed to delete contact. Please try again.');
                }
            });
        }
    });
// Block User Functionality
let currentBlockUserId = null;

$(document).on('click', '.block-user-btn', function() {
    const userId = $(this).data('user-id');
    const userName = $(this).data('name');
    
    currentBlockUserId = userId;
    $('#blockUserName').text(userName);
    $('#blockUserModal').modal('show');
});

// Unblock User Functionality (no modal needed for confirmation)
$(document).on('click', '.unblock-user-btn', function() {
    const userId = $(this).data('user-id');
    const userName = $(this).data('name');
    
    if (confirm(`Are you sure you want to unblock ${userName}?`)) {
        unblockUser(userId, userName, $(this));
    }
});

// Block form submission
$('#blockUserForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = {
        blocked_user_id: currentBlockUserId,
        reason: $('#blockReason').val()
    };
    
    $('#confirmBlockBtn').prop('disabled', true).html('<i class="bi bi-arrow-repeat spinner me-1"></i> Blocking...');

    $.ajax({
        url: '{{ route("blocks.store") }}',
        method: 'POST',
        data: formData,
        success: function(response) {
            $('#blockUserModal').modal('hide');
            showAlert('success', 'User blocked successfully!');
            
            // Update UI
            updateBlockUI(currentBlockUserId, true);
            filterContacts(); // Re-apply filters
        },
        error: function(xhr) {
            const error = xhr.responseJSON?.message || 'Failed to block user';
            showAlert('error', error);
        },
        complete: function() {
            $('#confirmBlockBtn').prop('disabled', false).html('<i class="bi bi-slash-circle me-1"></i> Block User');
        }
    });
});

// Unblock function
function unblockUser(userId, userName, $button) {
    $button.prop('disabled', true).html('<i class="bi bi-arrow-repeat spinner me-2"></i>Unblocking...');

    $.ajax({
        url: `/blocks/${userId}`,
        method: 'DELETE',
        success: function(response) {
            showAlert('success', 'User unblocked successfully!');
            
            // Update UI
            updateBlockUI(userId, false);
            filterContacts(); // Re-apply filters
        },
        error: function(xhr) {
            const error = xhr.responseJSON?.message || 'Failed to unblock user';
            showAlert('error', error);
            $button.prop('disabled', false).html('<i class="bi bi-unlock me-2"></i>Unblock User');
        }
    });
}

// Update UI helper function
function updateBlockUI(userId, isBlocked) {
    const $contactItem = $(`[data-contact-id]`).filter(function() {
        const $btn = $(this).find('.block-user-btn, .unblock-user-btn');
        return $btn.length && $btn.data('user-id') === userId;
    });
    
    if ($contactItem.length) {
        $contactItem.attr('data-blocked', isBlocked.toString());
        
        if (isBlocked) {
            // Change to unblock button
            $contactItem.find('.block-user-btn')
                .removeClass('block-user-btn text-warning')
                .addClass('unblock-user-btn text-success')
                .html('<i class="bi bi-unlock me-2"></i>Unblock User');
                
            // Add blocked badge if it doesn't exist
            if (!$contactItem.find('.badge.bg-danger').length) {
                $contactItem.find('h6').append(
                    '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 ms-2">Blocked</span>'
                );
            }
        } else {
            // Change to block button
            $contactItem.find('.unblock-user-btn')
                .removeClass('unblock-user-btn text-success')
                .addClass('block-user-btn text-warning')
                .html('<i class="bi bi-slash-circle me-2"></i>Block User');
                
            // Remove blocked badge
            $contactItem.find('.badge.bg-danger').remove();
        }
    }
}

// Modal cleanup
$('#blockUserModal').on('hidden.bs.modal', function() {
    currentBlockUserId = null;
    $('#blockUserForm')[0].reset();
});
    // Toggle Favorite
    $(document).on('click', '.favorite-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const contactId = $(this).data('id');
        const isCurrentlyFavorite = $(this).hasClass('active');
        const method = isCurrentlyFavorite ? 'DELETE' : 'POST';
        const url = `/contacts/${contactId}/favorite`;

        const $btn = $(this);
        const $icon = $btn.find('i');
        const $contactItem = $(`[data-contact-id="${contactId}"]`);

        $.ajax({
            url: url,
            method: method,
            beforeSend: function() {
                $btn.prop('disabled', true);
            },
            success: function(response) {
                if (method === 'POST') {
                    $btn.addClass('active');
                    $icon.removeClass('bi-star').addClass('bi-star-fill text-warning');
                    $contactItem.attr('data-favorite', 'true');
                    $contactItem.find('.bi-star').removeClass('bi-star').addClass('bi-star-fill text-warning');
                    showAlert('success', 'Contact added to favorites!');
                } else {
                    $btn.removeClass('active');
                    $icon.removeClass('bi-star-fill text-warning').addClass('bi-star');
                    $contactItem.attr('data-favorite', 'false');
                    $contactItem.find('.bi-star-fill').removeClass('bi-star-fill text-warning').addClass('bi-star');
                    showAlert('success', 'Contact removed from favorites!');
                }
                
                // Update filter display
                filterContacts();
            },
            error: function() {
                showAlert('error', 'Failed to update favorite status. Please try again.');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Bulk Actions
    $('#bulkDeleteBtn').on('click', function() {
        const selectedIds = getSelectedContactIds();
        
        if (selectedIds.length === 0) {
            showAlert('warning', 'Please select contacts to delete');
            return;
        }

        if (confirm(`Are you sure you want to delete ${selectedIds.length} contact(s)? This action cannot be undone.`)) {
            $.ajax({
                url: '/contacts/bulk-delete',
                method: 'POST',
                data: { contact_ids: selectedIds },
                beforeSend: function() {
                    showLoading();
                },
                success: function(response) {
                    selectedIds.forEach(id => {
                        $(`[data-contact-id="${id}"]`).slideUp(300, function() {
                            $(this).remove();
                        });
                    });
                    
                    showAlert('success', `${selectedIds.length} contact(s) deleted successfully!`);
                    resetBulkActions();
                    updateContactCount();
                    checkEmptyState();
                    filterContacts(); // Re-apply filters after bulk deletion
                },
                error: function() {
                    showAlert('error', 'Failed to delete contacts. Please try again.');
                },
                complete: function() {
                    hideLoading();
                }
            });
        }
    });

    $('#bulkFavoriteBtn').on('click', function() {
        const selectedIds = getSelectedContactIds();
        
        if (selectedIds.length === 0) {
            showAlert('warning', 'Please select contacts to add to favorites');
            return;
        }

        // Add all selected contacts to favorites
        let completed = 0;
        selectedIds.forEach(id => {
            $.ajax({
                url: `/contacts/${id}/favorite`,
                method: 'POST',
                success: function() {
                    $(`[data-contact-id="${id}"]`).attr('data-favorite', 'true')
                        .find('.favorite-btn').addClass('active')
                        .find('i').removeClass('bi-star').addClass('bi-star-fill text-warning');
                },
                complete: function() {
                    completed++;
                    if (completed === selectedIds.length) {
                        showAlert('success', `${selectedIds.length} contact(s) added to favorites!`);
                        resetBulkActions();
                        filterContacts();
                    }
                }
            });
        });
    });

    // Selection Management
    $(document).on('change', '.contact-checkbox', function() {
        toggleBulkActions();
        updateSelectionUI();
    });

    $('#selectAllContacts').on('change', function() {
        const isChecked = $(this).prop('checked');
        $('.contact-checkbox').prop('checked', isChecked);
        toggleBulkActions();
        updateSelectionUI();
    });

    $('#clearSelectionBtn').on('click', function() {
        resetBulkActions();
    });

    // Invite Contact
    $(document).on('click', '.invite-contact-btn', function() {
        const phone = $(this).data('phone');
        const name = $(this).data('name');
        
        $('#inviteContactName').text(name);
        $('#invitePhoneHint').text(`Phone: ${phone}`);
        
        // Set up SMS link
        const smsText = `Join me on {{ config('app.name', 'GekyChat') }}! Download the app to start chatting.`;
        $('#inviteSmsBtn').attr('href', `sms:${phone}?body=${encodeURIComponent(smsText)}`);
        
        // Set up share functionality
        if (navigator.share) {
            $('#inviteShareBtn').show().off('click').on('click', function() {
                navigator.share({
                    title: 'Join me on GekyChat',
                    text: smsText,
                    url: window.location.origin
                });
            });
        } else {
            $('#inviteShareBtn').hide();
        }
        
        // Set up copy link
        $('#inviteLinkInput').val(window.location.origin);
        $('#inviteCopyBtn').off('click').on('click', function() {
            $('#inviteLinkInput').select();
            document.execCommand('copy');
            $(this).html('<i class="bi bi-check me-1"></i> Copied!');
            setTimeout(() => {
                $('#inviteCopyBtn').html('<i class="bi bi-clipboard me-1"></i> Copy');
            }, 2000);
        });
        
        $('#inviteModal').modal('show');
    });

    // Character counters
    $('#display_name').on('input', function() {
        const maxLength = 255;
        const currentLength = $(this).val().length;
        const remaining = maxLength - currentLength;
        $('#name-char-count').text(`${remaining} characters left`).toggleClass('text-danger', remaining < 20);
    });

    $('#note').on('input', function() {
        const maxLength = 500;
        const currentLength = $(this).val().length;
        const remaining = maxLength - currentLength;
        $('#note-char-count').text(`${remaining} characters left`).toggleClass('text-danger', remaining < 50);
    });

    // Modal cleanup
    $('#addContactModal').on('hidden.bs.modal', function() {
        resetForm('#addContactForm');
    });

    // Helper Functions
    function getSelectedContactIds() {
        const selectedIds = [];
        $('.contact-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });
        return selectedIds;
    }

    function toggleBulkActions() {
        const selectedCount = $('.contact-checkbox:checked').length;
        if (selectedCount > 0) {
            $('#bulkActions').removeClass('d-none');
            $('#selectedCount').text(`${selectedCount} contact${selectedCount === 1 ? '' : 's'} selected`);
        } else {
            $('#bulkActions').addClass('d-none');
        }
    }

    function updateSelectionUI() {
        $('.contact-item').removeClass('selected');
        $('.contact-checkbox:checked').closest('.contact-item').addClass('selected');
    }

    function resetBulkActions() {
        $('.contact-checkbox').prop('checked', false);
        $('#selectAllContacts').prop('checked', false);
        $('#bulkActions').addClass('d-none');
        updateSelectionUI();
    }


    function checkEmptyState() {
        if ($('.contact-item').length === 0) {
            $('#empty-state').removeClass('d-none');
            $('#contacts-list').addClass('d-none');
            $('#bulkActions').addClass('d-none');
            $('#no-results-state').addClass('d-none');
        } else {
            $('#empty-state').addClass('d-none');
            $('#contacts-list').removeClass('d-none');
        }
    }

    function updateContactCount() {
        const totalCount = $('.contact-item').length;
        const visibleCount = $('.contact-item:visible').length;
        $('#visibleCount').text(visibleCount);
        
        if (totalCount === 0) {
            checkEmptyState();
        }
    }

    function addContactToDOM(contactData) {
        // Ensure contactData has all required fields
        if (!contactData || !contactData.id) {
            console.error('Invalid contact data:', contactData);
            return;
        }
        
        const contactSource = contactData.source || 'manual';
        const isGoogleContact = contactSource === 'google_sync';
        const isPhoneContact = contactSource === 'phone' || contactSource === 'device';
        const sourceBadge = isGoogleContact ? 
            '<span class="badge google-contact-badge small ms-2"><i class="bi bi-google me-1"></i>Google</span>' :
            (isPhoneContact ? 
            '<span class="badge phone-contact-badge small ms-2"><i class="bi bi-phone me-1"></i>Phone</span>' : '');
            
        const googleInfoButton = isGoogleContact ? 
            `<li>
                <button class="dropdown-item text-text google-contact-info-btn" 
                        data-phone="${contactData.phone || ''}"
                        data-name="${contactData.display_name || contactData.phone || ''}">
                    <i class="bi bi-info-circle me-2"></i>Sync Info
                </button>
            </li>` : '';
        
        const contactHtml = `
            <div class="list-group-item contact-item d-flex align-items-center px-0 py-3 bg-card border-border" 
                 data-contact-id="${contactData.id}"
                 data-name="${((contactData.display_name || '') + '').toLowerCase()}"
                 data-phone="${((contactData.phone || '') + '').toLowerCase()}"
                 data-registered="${contactData.is_registered ? 'true' : 'false'}"
                 data-favorite="${contactData.is_favorite ? 'true' : 'false'}"
                 data-source="${contactData.source || 'manual'}">
                
                <div class="me-3">
                    <input type="checkbox" class="form-check-input contact-checkbox" value="${contactData.id}">
                </div>

                <div class="avatar me-3" style="width: 40px; height: 40px;">
                    <div class="avatar-placeholder avatar-md">
                        ${contactData.display_name ? contactData.display_name.charAt(0).toUpperCase() : (contactData.phone || '').charAt(0).toUpperCase()}
                    </div>
                </div>

                <div class="flex-grow-1">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <h6 class="mb-0 fw-semibold text-text">
                            ${contactData.display_name || contactData.phone}
                            ${sourceBadge}
                        </h6>
                        <div class="d-flex align-items-center gap-2">
                            ${contactData.is_favorite ? '<i class="bi bi-star-fill text-warning" title="Favorite"></i>' : ''}
                            ${contactData.is_registered ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">On GekyChat</span>' : ''}
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <span class="text-muted small">${contactData.phone}</span>
                        ${contactData.note ? '<span class="text-muted small" title="' + contactData.note + '"><i class="bi bi-sticky me-1"></i>Has note</span>' : ''}
                    </div>
                </div>

                <div class="dropdown ms-3">
                    <button class="btn btn-sm btn-outline-secondary border-0 text-text" type="button" 
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end bg-card border-border">
                        ${contactData.is_registered ? 
                            `<li>
                                <a class="dropdown-item text-text" href="{{ route('chat.start') }}?user_id=${contactData.user_id}">
                                    <i class="bi bi-chat-dots me-2"></i>Send Message
                                </a>
                            </li>` :
                            `<li>
                                <button class="dropdown-item text-text invite-contact-btn" 
                                        data-phone="${contactData.phone}"
                                        data-name="${contactData.display_name || contactData.phone}">
                                    <i class="bi bi-share me-2"></i>Invite to GekyChat
                                </button>
                            </li>`
                        }
                        <li>
                            <button class="dropdown-item text-text favorite-btn ${contactData.is_favorite ? 'active' : ''}" 
                                    data-id="${contactData.id}">
                                <i class="bi bi-star${contactData.is_favorite ? '-fill' : ''} me-2 ${contactData.is_favorite ? 'text-warning' : ''}"></i>
                                ${contactData.is_favorite ? 'Remove from' : 'Add to'} Favorites
                            </button>
                        </li>
                        ${googleInfoButton}
                        <li><hr class="dropdown-divider border-border"></li>
                        <li>
                            <button class="dropdown-item text-danger delete-contact-btn" 
                                    data-id="${contactData.id}" 
                                    data-name="${contactData.display_name || contactData.phone}">
                                <i class="bi bi-trash me-2"></i>Delete Contact
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        `;
        
        if ($('#contacts-list').length) {
            $('#contacts-list').prepend(contactHtml);
        } else {
            $('#contacts-container').html(`<div class="list-group list-group-flush" id="contacts-list">${contactHtml}</div>`);
        }
        
        // Hide empty state if it was showing
        $('#empty-state').addClass('d-none');
        $('#contacts-list').removeClass('d-none');
    }

    function resetForm(selector) {
        $(selector)[0].reset();
        $('#name-char-count').text('255 characters left').removeClass('text-danger');
        $('#note-char-count').text('500 characters left').removeClass('text-danger');
    }

    function showLoading() {
        $('#loadingSpinner').removeClass('d-none');
    }

    function hideLoading() {
        $('#loadingSpinner').addClass('d-none');
    }

    // ============================================
    // GOOGLE SYNC STATUS
    // ============================================
    
    function loadGoogleSyncStatus() {
        // Check if Google sync status element exists
        if ($('#google-sync-status').length === 0) {
            return; // Google sync not available for this user
        }

        // Update sync status if user has Google access
        // The initial values are already rendered server-side
        // This function can be extended to fetch updated status via AJAX if needed
        // For now, it's just a placeholder to prevent the error
        
        // Note: To implement AJAX refresh of sync status, create a route and uncomment below:
        // Example implementation would use: url: '/api/google/sync/status'
    }

    // ============================================
    // INITIALIZATION
    // ============================================
    
    // Initialize on page load
    loadGoogleSyncStatus();
    updateContactCount();
    filterContacts();
});
</script>
@endpush