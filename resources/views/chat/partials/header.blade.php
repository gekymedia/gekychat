{{-- resources/views/chat/partials/header.blade.php --}}
@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    $headerData = $headerData ?? [
        'name' => __('Conversation'),
        'initial' => 'C',
        'avatar' => null,
        'online' => false,
        'lastSeen' => null,
        'userId' => null,
    ];

    $hasUserId = isset($headerData['userId']) && !empty($headerData['userId']);
@endphp

<header class="chat-header p-3 border-bottom d-flex align-items-center" role="banner" data-context="direct"
    data-user-id="{{ $hasUserId ? $headerData['userId'] : '' }}">

    {{-- Back Button (Mobile) --}}
    <button class="btn btn-sm btn-ghost d-md-none me-2" id="back-to-conversations"
        aria-label="{{ __('Back to conversations') }}" title="{{ __('Back to conversations') }}">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
    </button>

    {{-- User Info Section --}}
    <div class="d-flex align-items-center flex-grow-1 min-width-0">

        {{-- Avatar with Status --}}
        <div class="position-relative">
            @if (!empty($headerData['avatar']))
                <img src="{{ $headerData['avatar'] }}" alt="{{ __(':name avatar', ['name' => $headerData['name']]) }}"
                    class="avatar avatar-img me-3" loading="lazy" width="40" height="40"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="avatar me-3 rounded-circle bg-brand text-white" style="display: none;">
                    {{ $headerData['initial'] }}
                </div>
            @else
                <div class="avatar me-3 rounded-circle bg-brand text-white">
                    {{ $headerData['initial'] }}
                </div>
            @endif

            {{-- Online Status Indicator --}}
            @if ($headerData['online'])
                <span class="position-absolute bottom-0 end-0 bg-success rounded-circle border border-2 border-white"
                    style="width: 12px; height: 12px;" title="{{ __('Online') }}"
                    aria-label="{{ __('Online') }}"></span>
            @elseif($headerData['lastSeen'])
                <span class="position-absolute bottom-0 end-0 bg-secondary rounded-circle border border-2 border-white"
                    style="width: 12px; height: 12px;"
                    title="{{ __('Last seen :time', ['time' => $headerData['lastSeen']->diffForHumans()]) }}"
                    aria-label="{{ __('Last seen :time', ['time' => $headerData['lastSeen']->diffForHumans()]) }}"></span>
            @endif
        </div>

        {{-- Conversation Details --}}
        <div class="flex-grow-1 min-width-0">
            <h1 class="h5 mb-0 chat-header-name text-truncate" title="{{ $headerData['name'] }}">
                {{ $headerData['name'] }}
            </h1>
            <div class="d-flex align-items-center gap-2">
                {{-- Typing Indicator --}}
                <small class="muted typing-indicator" id="typing-indicator" style="display: none;" aria-live="polite">
                    <span class="typing-dots">
                        <span class="dot"></span>
                        <span class="dot"></span>
                        <span class="dot"></span>
                    </span>
                    {{ __('typing…') }}
                </small>

                {{-- Online Status --}}
                <small class="muted online-status" id="online-status">
                    @if ($headerData['online'])
                        <i class="bi bi-circle-fill text-success me-1" style="font-size: 0.5rem;"></i>
                        <span>{{ __('Online') }}</span>
                    @elseif($headerData['lastSeen'])
                        <i class="bi bi-clock me-1" style="font-size: 0.5rem;"></i>
                        <span>{{ __('Last seen :time', ['time' => $headerData['lastSeen']->diffForHumans()]) }}</span>
                    @else
                        <i class="bi bi-circle me-1" style="font-size: 0.5rem;"></i>
                        <span>{{ __('Offline') }}</span>
                    @endif
                </small>
            </div>
        </div>
    </div>

    {{-- Header Actions --}}
    <div class="d-flex align-items-center gap-2">

        {{-- Online Users --}}
        <div id="online-list" class="d-none d-md-flex align-items-center gap-2" aria-label="{{ __('Online users') }}">
        </div>

        {{-- Options Menu --}}
        <div class="dropdown chat-options-dropdown" id="chat-options-dropdown">
            <button class="btn btn-sm btn-ghost" data-bs-toggle="dropdown" aria-expanded="false"
                aria-label="{{ __('Chat options') }}" title="{{ __('Chat options') }}" id="chat-options-button">
                <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" role="menu">
                <li role="none">
                    @if ($hasUserId)
                        <button class="dropdown-item d-flex align-items-center gap-2 view-profile-btn"
                            data-user-id="{{ $headerData['userId'] }}" data-user-name="{{ $headerData['name'] }}"
                            data-user-avatar="{{ $headerData['avatar'] ?? '' }}"
                            data-user-initial="{{ $headerData['initial'] }}"
                            data-user-online="{{ $headerData['online'] ? '1' : '0' }}"
                            data-user-last-seen="{{ $headerData['lastSeen'] ? $headerData['lastSeen']->diffForHumans() : '' }}"
                            role="menuitem" tabindex="0">
                            <i class="bi bi-person" aria-hidden="true"></i>
                            <span>{{ __('View profile') }}</span>
                        </button>
                    @else
                        <span class="dropdown-item d-flex align-items-center gap-2 text-muted" role="menuitem"
                            aria-disabled="true">
                            <i class="bi bi-person" aria-hidden="true"></i>
                            <span>{{ __('View profile') }}</span>
                        </span>
                    @endif
                </li>
                <li role="none">
                    <button class="dropdown-item d-flex align-items-center gap-2" id="mute-chat-btn" role="menuitem"
                        tabindex="0">
                        <i class="bi bi-bell" aria-hidden="true"></i>
                        <span>{{ __('Mute notifications') }}</span>
                    </button>
                </li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li role="none">
                    <button class="dropdown-item d-flex align-items-center gap-2 text-danger" id="clear-chat-btn"
                        role="menuitem" tabindex="0">
                        <i class="bi bi-trash" aria-hidden="true"></i>
                        <span>{{ __('Clear chat') }}</span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</header>

{{-- Profile Modal --}}
<div class="modal fade" id="userProfileModal" tabindex="-1" aria-labelledby="userProfileModalLabel"
    aria-hidden="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            {{-- Modal Header --}}
            <div class="modal-header">
                <h5 class="modal-title" id="userProfileModalLabel">{{ __('User Profile') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="{{ __('Close') }}"></button>
            </div>

            {{-- Modal Body --}}
            <div class="modal-body">

                {{-- Loading State --}}
                <div id="profile-loading" class="text-center py-4" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">{{ __('Loading...') }}</span>
                    </div>
                    <p class="mt-2 text-muted">{{ __('Loading profile...') }}</p>
                </div>

                {{-- Profile Content --}}
                <div id="profile-content">

                    {{-- Basic Info --}}
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <img id="profile-avatar" src="" alt="{{ __('Profile avatar') }}"
                                class="avatar-img rounded-circle mb-3" width="80" height="80"
                                onerror="this.style.display='none'; document.getElementById('profile-initial').style.display='flex';">
                            <div id="profile-initial"
                                class="avatar rounded-circle bg-brand text-white d-none align-items-center justify-content-center mx-auto mb-3"
                                style="width: 80px; height: 80px; font-size: 1.5rem; font-weight: 600;">
                            </div>

                            {{-- Online Status --}}
                            <span id="profile-online-status"
                                class="position-absolute bottom-0 end-0 rounded-circle border border-3 border-white"
                                style="width: 16px; height: 16px; display: none;" title="{{ __('Online') }}"></span>
                        </div>

                        <h4 id="profile-name" class="mb-1"></h4>
                        <p id="profile-status" class="text-muted mb-3"></p>
                    </div>

                    {{-- Contact Information --}}
                    <div class="profile-info">
                        <h6 class="section-title mb-3">{{ __('Contact Information') }}</h6>
                        <div class="info-item mb-2">
                            <strong><i class="bi bi-telephone me-2"></i>{{ __('Phone:') }}</strong>
                            <span id="profile-phone" class="ms-2"></span>
                        </div>
                        <div class="info-item mb-2">
                            <strong><i class="bi bi-clock me-2"></i>{{ __('Last Seen:') }}</strong>
                            <span id="profile-last-seen" class="ms-2"></span>
                        </div>
                        <div class="info-item">
                            <strong><i class="bi bi-calendar me-2"></i>{{ __('Member Since:') }}</strong>
                            <span id="profile-member-since" class="ms-2"></span>
                        </div>
                    </div>

                    {{-- Contact Management --}}
                    <div class="profile-actions mt-4 pt-3 border-top">
                        <h6 class="section-title mb-3">{{ __('Contact Management') }}</h6>
                        <div id="contact-actions">
                            {{-- Contact actions will be dynamically inserted here --}}
                        </div>
                    </div>

                    {{-- Additional Actions --}}
                    <div class="additional-actions mt-3 pt-3 border-top">
                        <h6 class="section-title mb-3">{{ __('Actions') }}</h6>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" id="view-full-profile">
                                <i class="bi bi-person-square me-2"></i>{{ __('View Full Profile') }}
                            </button>
                            <button class="btn btn-outline-secondary" id="view-contact-info">
                                <i class="bi bi-info-circle me-2"></i>{{ __('Contact Details') }}
                            </button>
                            <button class="btn btn-outline-warning" id="block-user">
                                <i class="bi bi-slash-circle me-2"></i>{{ __('Block User') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    class ChatHeader {
        constructor() {
            this.modal = null;
            this.currentUser = null;
            this.isLoading = false;
            this.init();
        }

        init() {
            this.setupBackButton();
            this.setupProfileModal();
            this.setupDropdownActions();
            this.setupKeyboardNavigation();
            this.setupModalEvents();
            this.fixDropdownZIndex();
        }

        fixDropdownZIndex() {
            const dropdowns = document.querySelectorAll('.chat-options-dropdown');
            dropdowns.forEach(dropdown => {
                const menu = dropdown.querySelector('.dropdown-menu');
                if (menu) {
                    menu.style.zIndex = '1060';
                }
            });
        }

        setupBackButton() {
            document.getElementById('back-to-conversations')?.addEventListener('click', () => {
                window.history.back();
            });
        }

        setupProfileModal() {
            document.addEventListener('click', (e) => {
                if (e.target.closest('.view-profile-btn')) {
                    this.handleViewProfile(e.target.closest('.view-profile-btn'));
                }
            });
        }

        setupDropdownActions() {
            document.getElementById('mute-chat-btn')?.addEventListener('click', () => {
                this.toggleMuteChat();
            });

            document.getElementById('clear-chat-btn')?.addEventListener('click', () => {
                this.clearChat();
            });
        }

        setupKeyboardNavigation() {
            document.addEventListener('keydown', (e) => {
                this.handleKeyboardNavigation(e);
            });
        }

        setupModalEvents() {
            const profileModal = document.getElementById('userProfileModal');
            if (profileModal) {
                profileModal.addEventListener('shown.bs.modal', () => {
                    this.setupProfileModalKeyboard();
                });

                profileModal.addEventListener('hidden.bs.modal', () => {
                    this.cleanupProfileModal();
                });
            }
        }

        handleKeyboardNavigation(e) {
            if (e.key === 'Escape') {
                this.handleEscapeKey();
            }

            if (e.key === 'Enter' && e.target.closest('.dropdown-item')) {
                e.target.click();
            }

            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                this.handleDropdownNavigation(e);
            }
        }

        handleEscapeKey() {
            const modals = document.querySelectorAll('.modal.show');
            if (modals.length > 0) {
                const bootstrap = window.bootstrap;
                modals.forEach(modal => {
                    const bsModal = bootstrap.Modal.getInstance(modal);
                    if (bsModal) bsModal.hide();
                });
                return;
            }

            const dropdowns = document.querySelectorAll('.dropdown-menu.show');
            if (dropdowns.length > 0) {
                const bootstrap = window.bootstrap;
                dropdowns.forEach(dropdown => {
                    const bsDropdown = bootstrap.Dropdown.getInstance(dropdown.previousElementSibling);
                    if (bsDropdown) bsDropdown.hide();
                });
            }
        }

        handleDropdownNavigation(e) {
            const dropdown = e.target.closest('.dropdown');
            if (!dropdown) return;

            const items = dropdown.querySelectorAll('.dropdown-item:not([aria-disabled="true"])');
            if (items.length === 0) return;

            const currentIndex = Array.from(items).indexOf(document.activeElement);
            let nextIndex = 0;

            if (e.key === 'ArrowDown') {
                nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
            } else if (e.key === 'ArrowUp') {
                nextIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
            }

            e.preventDefault();
            items[nextIndex]?.focus();
        }

        setupProfileModalKeyboard() {
            const modal = document.getElementById('userProfileModal');
            const focusableElements = modal.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );

            if (focusableElements.length > 0) {
                focusableElements[0]?.focus();
            }
        }

        async handleViewProfile(button) {
            const userData = {
                id: button.dataset.userId,
                name: button.dataset.userName,
                avatar: button.dataset.userAvatar,
                initial: button.dataset.userInitial,
                online: button.dataset.userOnline === '1',
                lastSeen: button.dataset.userLastSeen
            };

            await this.showUserProfileModal(userData);
            this.closeDropdown(button);
        }

        closeDropdown(button) {
            const dropdown = bootstrap.Dropdown.getInstance(button.closest('.dropdown-toggle'));
            if (dropdown) dropdown.hide();
        }

        async showUserProfileModal(user) {
            this.showLoadingState(true);

            try {
                if (!user || !user.id) {
                    throw new Error('{{ __("Invalid user data") }}');
                }

                this.setBasicProfileInfo(user);
                const userDetails = await this.loadUserProfileData(user.id);
                
                const completeUserData = {
                    ...user,
                    ...userDetails
                };

                this.updateProfileModal(completeUserData);
            } catch (error) {
                this.handleProfileError(error);
            } finally {
                this.showLoadingState(false);
            }

            this.modal = new bootstrap.Modal(document.getElementById('userProfileModal'));
            this.modal.show();
        }

        setBasicProfileInfo(user) {
            document.getElementById('profile-name').textContent = user.name;

            const profileAvatar = document.getElementById('profile-avatar');
            const profileInitial = document.getElementById('profile-initial');

            if (user.avatar) {
                profileAvatar.src = user.avatar;
                profileAvatar.style.display = 'block';
                profileInitial.style.display = 'none';
            } else {
                profileAvatar.style.display = 'none';
                profileInitial.style.display = 'flex';
                profileInitial.textContent = user.initial;
            }

            this.updateOnlineStatus(user.online, user.lastSeen);
        }

        updateOnlineStatus(isOnline, lastSeen) {
            const onlineStatus = document.getElementById('profile-online-status');
            const statusText = document.getElementById('profile-status');
            const lastSeenElement = document.getElementById('profile-last-seen');

            if (isOnline) {
                onlineStatus.style.display = 'block';
                onlineStatus.className = onlineStatus.className.replace('bg-secondary', 'bg-success');
                statusText.textContent = '{{ __("Online") }}';
                lastSeenElement.textContent = '{{ __("Now") }}';
            } else {
                onlineStatus.style.display = 'block';
                onlineStatus.className = onlineStatus.className.replace('bg-success', 'bg-secondary');
                statusText.textContent = '{{ __("Offline") }}';
                lastSeenElement.textContent = lastSeen || '{{ __("Unknown") }}';
            }
        }

        async loadUserProfileData(userId) {
            try {
                const response = await fetch(`/api/users/${userId}/profile`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || '{{ __("Failed to load profile") }}');
                }

                return data.user;

            } catch (error) {
                console.error('Error loading user profile:', error);
                throw error;
            }
        }

        updateProfileModal(userDetails) {
            this.updateContactInfo(userDetails);
            this.setupContactActions(userDetails);
            this.setupAdditionalActions(userDetails);
        }

        updateContactInfo(userDetails) {
            const phoneElement = document.getElementById('profile-phone');
            const lastSeenElement = document.getElementById('profile-last-seen');
            const memberSinceElement = document.getElementById('profile-member-since');
            
            if (userDetails.phone) {
                phoneElement.textContent = userDetails.phone;
                phoneElement.className = 'ms-2';
            } else {
                phoneElement.textContent = '{{ __("Not available") }}';
                phoneElement.className = 'ms-2 text-muted';
            }
            
            if (userDetails.last_seen_at) {
                lastSeenElement.textContent = this.formatRelativeTime(userDetails.last_seen_at);
                lastSeenElement.className = 'ms-2';
            } else {
                lastSeenElement.textContent = '{{ __("Unknown") }}';
                lastSeenElement.className = 'ms-2 text-muted';
            }
            
            if (userDetails.created_at) {
                memberSinceElement.textContent = this.formatDate(userDetails.created_at);
                memberSinceElement.className = 'ms-2';
            } else {
                memberSinceElement.textContent = '{{ __("Unknown") }}';
                memberSinceElement.className = 'ms-2 text-muted';
            }

            this.updateOnlineStatus(userDetails.is_online, userDetails.last_seen_at);
        }

        setupContactActions(userDetails) {
            const contactActionsContainer = document.getElementById('contact-actions');
            
            if (userDetails.is_contact) {
                contactActionsContainer.innerHTML = `
                    <div class="contact-status-alert alert alert-success mb-3">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        {{ __("Saved in your contacts") }}
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" id="edit-contact-btn">
                            <i class="bi bi-pencil-square me-2"></i>{{ __("Edit Contact") }}
                        </button>
                        <button class="btn btn-outline-danger" id="delete-contact-btn">
                            <i class="bi bi-trash me-2"></i>{{ __("Delete Contact") }}
                        </button>
                        <button class="btn btn-outline-info" id="share-contact-btn">
                            <i class="bi bi-share me-2"></i>{{ __("Share Contact") }}
                        </button>
                    </div>
                `;

                document.getElementById('edit-contact-btn').onclick = () => {
                    this.editContact(userDetails);
                };

                document.getElementById('delete-contact-btn').onclick = () => {
                    this.deleteContact(userDetails.contact_data.id, userDetails.name);
                };

                document.getElementById('share-contact-btn').onclick = () => {
                    this.shareContact(userDetails);
                };

            } else {
                contactActionsContainer.innerHTML = `
                    <div class="contact-status-alert alert alert-warning mb-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        {{ __("Not in your contacts") }}
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-success mb-2" id="add-to-contacts-btn">
                            <i class="bi bi-person-plus me-2"></i>{{ __("Add to Contacts") }}
                        </button>
                        <button class="btn btn-outline-info" id="share-profile-btn">
                            <i class="bi bi-share me-2"></i>{{ __("Share Profile") }}
                        </button>
                    </div>
                `;

                document.getElementById('add-to-contacts-btn').onclick = () => {
                    this.addToContacts(userDetails);
                };

                document.getElementById('share-profile-btn').onclick = () => {
                    this.shareProfile(userDetails);
                };
            }
        }

        setupAdditionalActions(userDetails) {
            document.getElementById('view-full-profile').onclick = () => {
                if (userDetails.id) {
                    window.open(`/users/${userDetails.id}`, '_blank');
                } else {
                    this.showToast('{{ __("User profile not available") }}', 'error');
                }
            };

            document.getElementById('view-contact-info').onclick = () => {
                this.showContactDetails(userDetails);
            };

            document.getElementById('block-user').onclick = () => {
                this.blockUser(userDetails.id, userDetails.name);
            };
        }

        addToContacts(userDetails) {
            const modalHtml = `
                <div class="modal fade" id="addContactModal" tabindex="-1" aria-labelledby="addContactModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addContactModalLabel">
                                    <i class="bi bi-person-plus me-2"></i>
                                    {{ __("Add to Contacts") }}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                            </div>
                            <div class="modal-body">
                                <form id="addContactForm">
                                    <div id="form-errors" class="alert alert-danger d-none"></div>
                                    <div class="mb-3">
                                        <label class="form-label">{{ __("Display Name") }} *</label>
                                        <input type="text" class="form-control" name="display_name" value="${userDetails.name}" required
                                               placeholder="{{ __("Enter contact name") }}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">{{ __("Phone") }}</label>
                                        <input type="text" class="form-control" value="${userDetails.phone || '{{ __("Not available") }}'}" disabled>
                                        <small class="text-muted">{{ __("Phone number cannot be changed") }}</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">{{ __("Note") }} ({{ __("Optional") }})</label>
                                        <textarea class="form-control" name="note" rows="3" 
                                                  placeholder="{{ __("Add a note about this contact...") }}"></textarea>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="is_favorite" id="isFavorite">
                                        <label class="form-check-label" for="isFavorite">
                                            <i class="bi bi-star me-1"></i>
                                            {{ __("Add to favorites") }}
                                        </label>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-2"></i>
                                    {{ __("Cancel") }}
                                </button>
                                <button type="button" class="btn btn-primary" id="saveContactBtn">
                                    <i class="bi bi-check-circle me-2"></i>
                                    {{ __("Save Contact") }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('addContactModal'));
            
            document.getElementById('saveContactBtn').onclick = () => {
                this.saveContactToServer(userDetails, modal);
            };

            modal.show();
            
            document.getElementById('addContactModal').addEventListener('hidden.bs.modal', function () {
                this.remove();
            });
        }

        async saveContactToServer(userDetails, modal) {
            const form = document.getElementById('addContactForm');
            const formData = new FormData(form);
            const errorsContainer = document.getElementById('form-errors');
            
            const displayName = formData.get('display_name').trim();
            if (!displayName) {
                this.showFormError('{{ __("Display name is required") }}');
                return;
            }

            const contactData = {
                display_name: displayName,
                phone: userDetails.phone,
                contact_user_id: userDetails.id,
                note: formData.get('note'),
                is_favorite: formData.get('is_favorite') === 'on'
            };

            try {
                const response = await fetch('/api/contacts', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(contactData)
                });

                const data = await response.json();

                if (data.success) {
                    modal.hide();
                    this.showToast('{{ __("Contact saved successfully!") }}', 'success');
                    
                    if (this.currentUser) {
                        const updatedDetails = await this.loadUserProfileData(this.currentUser.id);
                        this.updateProfileModal(updatedDetails);
                    }
                } else {
                    this.showFormError(data.message || '{{ __("Failed to save contact") }}');
                }
            } catch (error) {
                console.error('Error saving contact:', error);
                this.showFormError('{{ __("Failed to save contact") }}');
            }
        }

        editContact(userDetails) {
            const contact = userDetails.contact_data;
            const modalHtml = `
                <div class="modal fade" id="editContactModal" tabindex="-1" aria-labelledby="editContactModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editContactModalLabel">
                                    <i class="bi bi-pencil-square me-2"></i>
                                    {{ __("Edit Contact") }}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                            </div>
                            <div class="modal-body">
                                <form id="editContactForm">
                                    <div id="edit-form-errors" class="alert alert-danger d-none"></div>
                                    <div class="mb-3">
                                        <label class="form-label">{{ __("Display Name") }} *</label>
                                        <input type="text" class="form-control" name="display_name" 
                                               value="${contact.display_name || userDetails.name}" required
                                               placeholder="{{ __("Enter contact name") }}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">{{ __("Phone") }}</label>
                                        <input type="text" class="form-control" value="${userDetails.phone || '{{ __("Not available") }}'}" disabled>
                                        <small class="text-muted">{{ __("Phone number cannot be changed") }}</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">{{ __("Note") }} ({{ __("Optional") }})</label>
                                        <textarea class="form-control" name="note" rows="3" 
                                                  placeholder="{{ __("Add a note about this contact...") }}">${contact.note || ''}</textarea>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="is_favorite" id="editIsFavorite" ${contact.is_favorite ? 'checked' : ''}>
                                        <label class="form-check-label" for="editIsFavorite">
                                            <i class="bi bi-star me-1"></i>
                                            {{ __("Add to favorites") }}
                                        </label>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-2"></i>
                                    {{ __("Cancel") }}
                                </button>
                                <button type="button" class="btn btn-primary" id="updateContactBtn">
                                    <i class="bi bi-check-circle me-2"></i>
                                    {{ __("Update Contact") }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('editContactModal'));
            
            document.getElementById('updateContactBtn').onclick = () => {
                this.updateContactOnServer(contact.id, userDetails, modal);
            };

            modal.show();
            
            document.getElementById('editContactModal').addEventListener('hidden.bs.modal', function () {
                this.remove();
            });
        }

        async updateContactOnServer(contactId, userDetails, modal) {
            const form = document.getElementById('editContactForm');
            const formData = new FormData(form);
            const errorsContainer = document.getElementById('edit-form-errors');
            
            const displayName = formData.get('display_name').trim();
            if (!displayName) {
                this.showFormError('{{ __("Display name is required") }}', 'edit-form-errors');
                return;
            }

            const contactData = {
                display_name: displayName,
                note: formData.get('note'),
                is_favorite: formData.get('is_favorite') === 'on',
                _method: 'PUT'
            };

            try {
                const response = await fetch(`/api/contacts/${contactId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(contactData)
                });

                const data = await response.json();

                if (data.success) {
                    modal.hide();
                    this.showToast('{{ __("Contact updated successfully!") }}', 'success');
                    
                    if (this.currentUser) {
                        const updatedDetails = await this.loadUserProfileData(this.currentUser.id);
                        this.updateProfileModal(updatedDetails);
                    }
                } else {
                    this.showFormError(data.message || '{{ __("Failed to update contact") }}', 'edit-form-errors');
                }
            } catch (error) {
                console.error('Error updating contact:', error);
                this.showFormError('{{ __("Failed to update contact") }}', 'edit-form-errors');
            }
        }

       async deleteContact(contactId, userName) {
    // Use a safer approach for the confirmation message
    const message = `{{ __("Are you sure you want to delete :name from your contacts?") }}`.replace(':name', userName);
    
    if (!confirm(message)) {
        return;
    }

    try {
        const response = await fetch(`/api/contacts/${contactId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        const data = await response.json();

        if (data.success) {
            this.showToast('{{ __("Contact deleted successfully") }}', 'success');
            
            // Refresh the profile to update contact status
            if (this.currentUser) {
                const updatedDetails = await this.loadUserProfileData(this.currentUser.id);
                this.updateProfileModal(updatedDetails);
            }
        } else {
            this.showToast(data.message || '{{ __("Failed to delete contact") }}', 'error');
        }
    } catch (error) {
        console.error('Error deleting contact:', error);
        this.showToast('{{ __("Failed to delete contact") }}', 'error');
    }
}
        shareContact(userDetails) {
            const contact = userDetails.contact_data;
            const shareText = `Contact: ${contact.display_name}\nPhone: ${userDetails.phone}\n${contact.note ? `Note: ${contact.note}` : ''}`;
            
            this.shareContent(shareText, `Share ${contact.display_name}'s Contact`);
        }

        shareProfile(userDetails) {
            const shareText = `Profile: ${userDetails.name}\nPhone: ${userDetails.phone}`;
            this.shareContent(shareText, `Share ${userDetails.name}'s Profile`);
        }

        shareContent(text, title) {
            if (navigator.share) {
                navigator.share({
                    title: title,
                    text: text
                }).catch(error => {
                    console.log('Error sharing:', error);
                    this.copyToClipboard(text);
                });
            } else {
                this.copyToClipboard(text);
            }
        }

        showContactDetails(user) {
            const modalHtml = `
                <div class="modal fade" id="contactDetailsModal" tabindex="-1" aria-labelledby="contactDetailsModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="contactDetailsModalLabel">
                                    <i class="bi bi-person-lines-fill me-2"></i>
                                    {{ __("Contact Details") }}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                            </div>
                            <div class="modal-body">
                                <div class="contact-details-container">
                                    <div class="text-center mb-4">
                                        <div class="position-relative d-inline-block">
                                            ${user.avatar ? 
                                                `<img src="${user.avatar}" alt="${user.name}" class="rounded-circle mb-3" width="80" height="80" 
                                                      onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">` : 
                                                ''
                                            }
                                            <div class="rounded-circle bg-brand text-white d-flex align-items-center justify-content-center mx-auto mb-3" 
                                                 style="width: 80px; height: 80px; font-size: 1.5rem; font-weight: 600; ${user.avatar ? 'display: none;' : ''}">
                                                ${user.initial || user.name.charAt(0).toUpperCase()}
                                            </div>
                                        </div>
                                        <h4 class="mb-2">${user.name}</h4>
                                        <p class="text-muted mb-0">${user.online ? '{{ __("Online") }}' : '{{ __("Offline") }}'}</p>
                                    </div>
                                    
                                    <div class="contact-info-section">
                                        <h6 class="section-title mb-3">
                                            <i class="bi bi-info-circle me-2"></i>
                                            {{ __("Contact Information") }}
                                        </h6>
                                        <div class="info-grid">
                                            <div class="info-item">
                                                <div class="info-label">
                                                    <i class="bi bi-person me-2"></i>
                                                    {{ __("Name") }}
                                                </div>
                                                <div class="info-value">${user.name}</div>
                                            </div>
                                            <div class="info-item">
                                                <div class="info-label">
                                                    <i class="bi bi-telephone me-2"></i>
                                                    {{ __("Phone") }}
                                                </div>
                                                <div class="info-value">
                                                    ${user.phone || '<span class="text-muted">{{ __("Not available") }}</span>'}
                                                </div>
                                            </div>
                                            <div class="info-item">
                                                <div class="info-label">
                                                    <i class="bi bi-clock me-2"></i>
                                                    {{ __("Last Seen") }}
                                                </div>
                                                <div class="info-value">
                                                    ${user.last_seen ? this.formatRelativeTime(user.last_seen) : '<span class="text-muted">{{ __("Unknown") }}</span>'}
                                                </div>
                                            </div>
                                            <div class="info-item">
                                                <div class="info-label">
                                                    <i class="bi bi-calendar me-2"></i>
                                                    {{ __("Member Since") }}
                                                </div>
                                                <div class="info-value">
                                                    ${user.member_since ? this.formatDate(user.member_since) : '<span class="text-muted">{{ __("Unknown") }}</span>'}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-2"></i>
                                    {{ __("Close") }}
                                </button>
                                ${user.phone ? `
                                <button type="button" class="btn btn-outline-primary" id="copyPhoneNumber">
                                    <i class="bi bi-clipboard me-2"></i>
                                    {{ __("Copy Phone") }}
                                </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            const existingModal = document.getElementById('contactDetailsModal');
            if (existingModal) {
                existingModal.remove();
            }

            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modalElement = document.getElementById('contactDetailsModal');
            const modal = new bootstrap.Modal(modalElement);
            
            modalElement.addEventListener('shown.bs.modal', () => {
                modalElement.querySelector('.btn-close').focus();
            });

            const copyPhoneBtn = document.getElementById('copyPhoneNumber');
            if (copyPhoneBtn && user.phone) {
                copyPhoneBtn.addEventListener('click', () => {
                    this.copyToClipboard(user.phone);
                });
            }

            modalElement.addEventListener('hidden.bs.modal', () => {
                setTimeout(() => {
                    modalElement.remove();
                }, 300);
            });

            modal.show();
        }

        showFormError(message, containerId = 'form-errors') {
            const errorsContainer = document.getElementById(containerId);
            errorsContainer.textContent = message;
            errorsContainer.classList.remove('d-none');
        }

        formatRelativeTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);

            if (diffInSeconds < 60) return '{{ __("Just now") }}';
            if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' {{ __("minutes ago") }}';
            if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' {{ __("hours ago") }}';
            if (diffInSeconds < 604800) return Math.floor(diffInSeconds / 86400) + ' {{ __("days ago") }}';

            return this.formatDate(timestamp);
        }

        formatDate(timestamp) {
            return new Date(timestamp).toLocaleDateString();
        }

        showLoadingState(show) {
            const loadingElement = document.getElementById('profile-loading');
            const contentElement = document.getElementById('profile-content');

            if (show) {
                loadingElement.style.display = 'block';
                contentElement.style.display = 'none';
            } else {
                loadingElement.style.display = 'none';
                contentElement.style.display = 'block';
            }
        }

        handleProfileError(error) {
            console.error('Error loading profile:', error);
            document.getElementById('profile-phone').textContent = '{{ __("Error loading") }}';
            document.getElementById('profile-member-since').textContent = '{{ __("Error loading") }}';
            this.showToast('{{ __("Failed to load profile") }}', 'error');
        }

        blockUser(userId, userName) {
           if (!confirm(`{{ __("Are you sure you want to delete :name from your contacts?") }}`.replace(':name', userName))) {
        return;
    }
        }

        toggleMuteChat() {
            this.showToast('{{ __("Chat notifications muted") }}', 'info');
        }

        clearChat() {
            if (confirm('{{ __("Are you sure you want to clear this chat? This action cannot be undone.") }}')) {
                this.showToast('{{ __("Chat cleared") }}', 'info');
            }
        }

        copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                this.showToast('{{ __("Copied to clipboard") }}', 'success');
            }).catch(err => {
                console.error('Failed to copy: ', err);
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    this.showToast('{{ __("Copied to clipboard") }}', 'success');
                } catch (fallbackErr) {
                    console.error('Fallback copy failed: ', fallbackErr);
                    this.showToast('{{ __("Failed to copy") }}', 'error');
                }
                document.body.removeChild(textArea);
            });
        }

        showToast(message, type = 'info') {
            // Simple toast implementation - you can replace with your preferred toast library
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-${this.getToastIcon(type)} me-2"></i>
                    <span>${message}</span>
                </div>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        getToastIcon(type) {
            const icons = {
                success: 'check-circle',
                error: 'exclamation-circle',
                warning: 'exclamation-triangle',
                info: 'info-circle'
            };
            return icons[type] || 'info-circle';
        }

        cleanupProfileModal() {
            this.currentUser = null;
            this.isLoading = false;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        window.chatHeader = new ChatHeader();
    });
</script>

<style>
    .chat-header[data-context="direct"] {
        background: var(--card);
        border-bottom: 1px solid var(--border);
        min-height: 70px;
        backdrop-filter: blur(10px);
        position: relative;
        z-index: 1030;
    }

    .chat-header-name {
        font-weight: 600;
        letter-spacing: 0.2px;
    }

    .typing-dots {
        display: inline-flex;
        gap: 2px;
        margin-left: 4px;
    }

    .typing-dots .dot {
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: var(--wa-muted);
        animation: typing-bounce 1.4s infinite ease-in-out;
    }

    .typing-dots .dot:nth-child(1) { animation-delay: -0.32s; }
    .typing-dots .dot:nth-child(2) { animation-delay: -0.16s; }
    .typing-dots .dot:nth-child(3) { animation-delay: 0s; }

    @keyframes typing-bounce {
        0%, 80%, 100% {
            transform: scale(0.8);
            opacity: 0.5;
        }
        40% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .chat-options-dropdown {
        position: relative;
        z-index: 1060;
    }

    .chat-options-dropdown .dropdown-menu {
        z-index: 1060 !important;
        position: absolute;
        top: 100%;
        right: 0;
        left: auto;
        margin-top: 0.125rem;
    }

    .section-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .info-item {
        display: flex;
        align-items: center;
        padding: 8px 0;
    }

    .profile-actions .btn,
    .additional-actions .btn {
        border-radius: 8px;
    }

    .dropdown-item:focus,
    .btn:focus {
        outline: 2px solid var(--primary);
        outline-offset: 2px;
    }

    .modal .btn:focus {
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    .contact-status-alert {
        font-size: 0.875rem;
    }

    .contact-details-container {
        max-height: 70vh;
        overflow-y: auto;
    }

    .info-grid {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 12px;
        background: var(--bg);
        border-radius: 8px;
        border: 1px solid var(--border);
        transition: all 0.2s ease;
    }

    .info-item:hover {
        background: var(--card);
        border-color: var(--primary);
    }

    .info-label {
        font-weight: 600;
        color: var(--text);
        display: flex;
        align-items: center;
        min-width: 120px;
    }

    .info-value {
        text-align: right;
        color: var(--text);
        word-break: break-word;
        max-width: 200px;
    }

    @media (max-width: 768px) {
        .chat-header[data-context="direct"] {
            padding: 12px;
        }

        #userProfileModal .modal-dialog {
            margin: 1rem;
        }

        .chat-options-dropdown .dropdown-menu {
            position: fixed;
            top: auto;
            bottom: 0;
            left: 0;
            right: 0;
            margin: 0;
            border-radius: 12px 12px 0 0;
            transform: none !important;
        }
    }

    @media (max-width: 576px) {
        .info-item {
            flex-direction: column;
            gap: 8px;
            align-items: flex-start;
        }

        .info-value {
            text-align: left;
            max-width: 100%;
        }

        .modal-footer {
            flex-direction: column;
            gap: 8px;
        }

        .modal-footer .btn {
            width: 100%;
        }
    }

    .modal.fade .modal-dialog {
        transform: scale(0.9);
        transition: transform 0.2s ease-out;
    }

    .modal.show .modal-dialog {
        transform: scale(1);
    }
</style>