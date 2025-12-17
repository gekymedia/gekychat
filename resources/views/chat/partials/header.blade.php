{{-- resources/views/chat/partials/header.blade.php --}}
@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;
    use Carbon\Carbon;

    // Ensure all required keys exist in headerData
    $defaultHeaderData = [
        'name' => __('Conversation'),
        'initial' => 'C',
        'avatar' => null,
        'online' => false,
        'lastSeen' => null,
        'userId' => null,
        'phone' => null,
        'created_at' => null,
    ];
    
    // Merge with default values to ensure all keys exist
    $headerData = array_merge($defaultHeaderData, $headerData ?? []);
    $hasUserId = isset($headerData['userId']) && !empty($headerData['userId']);

    // Format dates for display - safely handle Carbon instances and strings
    $lastSeenFormatted = null;
    $isCurrentlyOnline = false;
    
    if (!empty($headerData['lastSeen'])) {
        $lastSeen = $headerData['lastSeen'];
        if ($lastSeen instanceof \Carbon\Carbon) {
            $lastSeenFormatted = $lastSeen->diffForHumans();
            // User is considered online if last seen within 2 minutes
            $isCurrentlyOnline = $lastSeen->gt(now()->subMinutes(2));
        } elseif (is_string($lastSeen)) {
            $carbonLastSeen = Carbon::parse($lastSeen);
            $lastSeenFormatted = $carbonLastSeen->diffForHumans();
            $isCurrentlyOnline = $carbonLastSeen->gt(now()->subMinutes(2));
        }
    }

    // Override with explicit online status if provided
    if (isset($headerData['online'])) {
        $isCurrentlyOnline = (bool) $headerData['online'];
    }

    $memberSinceFormatted = null;
    if (!empty($headerData['created_at'])) {
        $createdAt = $headerData['created_at'];
        if ($createdAt instanceof \Carbon\Carbon) {
            $memberSinceFormatted = $createdAt->format('M j, Y');
        } elseif (is_string($createdAt)) {
            $memberSinceFormatted = Carbon::parse($createdAt)->format('M j, Y');
        }
    }

    // Check if user is already in contacts
    $isContact = false;
    $contactData = null;
    if ($hasUserId) {
        try {
            $currentUser = auth()->user();
            $isContact = $currentUser->isContact($headerData['userId']);
            $contactData = $currentUser->getContact($headerData['userId']);
        } catch (\Exception $e) {
            // Fallback if contact methods don't exist
            $isContact = false;
            $contactData = null;
        }
    }

    // Prepare user data for JavaScript
    $userDataForJs = [
        'id' => $headerData['userId'],
        'name' => $headerData['name'],
        'avatar' => $headerData['avatar'],
        'initial' => $headerData['initial'],
        'online' => $isCurrentlyOnline,
        'lastSeen' => $lastSeenFormatted,
        'phone' => $headerData['phone'],
        'createdAt' => $memberSinceFormatted,
        'isContact' => $isContact,
        'contactId' => $contactData ? $contactData->id : null
    ];
@endphp

<header class="chat-header p-3 border-bottom d-flex align-items-center" role="banner" data-context="direct"
    data-user-id="{{ $hasUserId ? $headerData['userId'] : '' }}" 
    data-user-data="{{ htmlspecialchars(json_encode($userDataForJs), ENT_QUOTES, 'UTF-8') }}"
    data-is-contact="{{ $isContact ? '1' : '0' }}" 
    data-contact-id="{{ $contactData ? $contactData->id : '' }}">

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
                <img src="{{ $headerData['avatar'] }}" 
                     alt="{{ __(':name avatar', ['name' => $headerData['name']]) }}"
                     class="rounded-circle" 
                     style="width: 40px; height: 40px; object-fit: cover; margin-right: 12px;"
                     loading="lazy"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="avatar-placeholder avatar-md" style="margin-right: 12px; display: none;">
                    {{ $headerData['initial'] }}
                </div>
            @else
                <div class="avatar-placeholder avatar-md" style="margin-right: 12px;">
                    {{ $headerData['initial'] }}
                </div>
            @endif

            {{-- Online Status Indicator --}}
            @if ($isCurrentlyOnline)
                <span class="position-absolute bottom-0 end-0 bg-success rounded-circle border border-2 border-white online-indicator"
                    style="width: 12px; height: 12px;" 
                    title="{{ __('Online') }}"
                    aria-label="{{ __('Online') }}"
                    data-online="true"></span>
            @elseif($lastSeenFormatted)
                <span class="position-absolute bottom-0 end-0 bg-secondary rounded-circle border border-2 border-white online-indicator"
                    style="width: 12px; height: 12px;"
                    title="{{ __('Last seen :time', ['time' => $lastSeenFormatted]) }}"
                    aria-label="{{ __('Last seen :time', ['time' => $lastSeenFormatted]) }}"
                    data-online="false"></span>
            @else
                <span class="position-absolute bottom-0 end-0 bg-secondary rounded-circle border border-2 border-white online-indicator"
                    style="width: 12px; height: 12px;"
                    title="{{ __('Offline') }}"
                    aria-label="{{ __('Offline') }}"
                    data-online="false"></span>
            @endif
        </div>

        {{-- Conversation Details --}}
        <div class="flex-grow-1 min-width-0">
            <h1 class="h5 mb-0 chat-header-name text-truncate" title="{{ $headerData['name'] }}">
                {{ $headerData['name'] }}
                @if ($isContact && $contactData && $contactData->is_favorite)
                    <i class="bi bi-star-fill text-warning ms-1" style="font-size: 0.8rem;"
                        title="{{ __('Favorite Contact') }}"
                        aria-label="{{ __('Favorite Contact') }}"></i>
                @endif
            </h1>
            <div class="d-flex align-items-center gap-2 flex-wrap">
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
                    @if ($isCurrentlyOnline)
                        <i class="bi bi-circle-fill text-success me-1" style="font-size: 0.5rem;"></i>
                        <span class="online-status-text">{{ __('Online') }}</span>
                    @elseif($lastSeenFormatted)
                        <i class="bi bi-clock me-1" style="font-size: 0.5rem;"></i>
                        <span class="online-status-text">{{ __('Last seen :time', ['time' => $lastSeenFormatted]) }}</span>
                    @else
                        <i class="bi bi-circle me-1" style="font-size: 0.5rem;"></i>
                        <span class="online-status-text">{{ __('Offline') }}</span>
                    @endif
                </small>

                {{-- Contact Status Badge --}}
                @if ($hasUserId)
                    <small class="muted contact-status-badge" id="contact-status-badge">
                        @if ($isContact)
                            <i class="bi bi-person-check-fill text-success me-1" style="font-size: 0.5rem;"></i>
                            <span>{{ __('In your contacts') }}</span>
                        @else
                            <i class="bi bi-person-plus text-muted me-1" style="font-size: 0.5rem;"></i>
                            <span>{{ __('Not in contacts') }}</span>
                        @endif
                    </small>
                @endif
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
                            data-user-id="{{ $headerData['userId'] }}" 
                            data-user-name="{{ $headerData['name'] }}"
                            data-user-avatar="{{ $headerData['avatar'] ?? '' }}"
                            data-user-initial="{{ $headerData['initial'] }}"
                            data-user-online="{{ $isCurrentlyOnline ? '1' : '0' }}"
                            data-user-last-seen="{{ $lastSeenFormatted }}"
                            data-user-phone="{{ $headerData['phone'] ?? '' }}"
                            data-user-created-at="{{ $memberSinceFormatted }}"
                            data-is-contact="{{ $isContact ? '1' : '0' }}"
                            data-contact-id="{{ $contactData ? $contactData->id : '' }}" 
                            role="menuitem"
                            tabindex="0">
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

                {{-- Contact Management Actions --}}
                @if ($hasUserId && $headerData['userId'] != auth()->id())
                    <li role="separator">
                        <hr class="dropdown-divider">
                    </li>
                    <li role="none" class="contact-management-section">
                        @if ($isContact)
                            {{-- Already in contacts - show management options --}}
                            <button class="dropdown-item d-flex align-items-center gap-2 text-success"
                                id="edit-contact-btn" 
                                data-contact-id="{{ $contactData ? $contactData->id : '' }}"
                                role="menuitem" 
                                tabindex="0">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                <span>{{ __('Edit contact info') }}</span>
                            </button>
                            @if ($contactData && $contactData->is_favorite)
                                <button class="dropdown-item d-flex align-items-center gap-2" id="remove-favorite-btn"
                                    data-contact-id="{{ $contactData->id }}" 
                                    role="menuitem" 
                                    tabindex="0">
                                    <i class="bi bi-star" aria-hidden="true"></i>
                                    <span>{{ __('Remove from favorites') }}</span>
                                </button>
                            @else
                                <button class="dropdown-item d-flex align-items-center gap-2" id="add-favorite-btn"
                                    data-contact-id="{{ $contactData ? $contactData->id : '' }}" 
                                    role="menuitem"
                                    tabindex="0">
                                    <i class="bi bi-star-fill" aria-hidden="true"></i>
                                    <span>{{ __('Add to favorites') }}</span>
                                </button>
                            @endif
                            <button class="dropdown-item d-flex align-items-center gap-2 text-danger"
                                id="remove-contact-btn" 
                                data-contact-id="{{ $contactData ? $contactData->id : '' }}"
                                role="menuitem" 
                                tabindex="0">
                                <i class="bi bi-person-dash" aria-hidden="true"></i>
                                <span>{{ __('Remove from contacts') }}</span>
                            </button>
                        @else
                            {{-- Not in contacts - show add option --}}
                            <button class="dropdown-item d-flex align-items-center gap-2 text-primary"
                                id="add-contact-btn" 
                                data-user-id="{{ $headerData['userId'] }}"
                                data-user-name="{{ $headerData['name'] }}"
                                data-user-phone="{{ $headerData['phone'] ?? '' }}" 
                                role="menuitem" 
                                tabindex="0">
                                <i class="bi bi-person-plus" aria-hidden="true"></i>
                                <span>{{ __('Add to your contacts') }}</span>
                            </button>
                        @endif
                    </li>
                @endif

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
                {{-- Block user option (DMs only) --}}
                @if ($hasUserId && $headerData['userId'] != auth()->id())
                <li role="none">
                    <button class="dropdown-item d-flex align-items-center gap-2 text-danger" id="block-user-btn"
                        data-user-id="{{ $headerData['userId'] }}"
                        role="menuitem" tabindex="0">
                        <i class="bi bi-slash-circle" aria-hidden="true"></i>
                        <span>{{ __('Block user') }}</span>
                    </button>
                </li>
                <li role="none">
                    <button class="dropdown-item d-flex align-items-center gap-2 text-warning" id="report-user-btn"
                        data-user-id="{{ $headerData['userId'] }}"
                        role="menuitem" tabindex="0">
                        <i class="bi bi-flag" aria-hidden="true"></i>
                        <span>{{ __('Report user') }}</span>
                    </button>
                </li>
                @endif
            </ul>
        </div>
    </div>
</header>

{{-- Profile Modal --}}
<div class="modal fade" id="userProfileModal" tabindex="-1" aria-labelledby="userProfileModalLabel"
    aria-hidden="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userProfileModalLabel">{{ __('User Profile') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <div id="profile-loading" class="text-center py-4" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">{{ __('Loading...') }}</span>
                    </div>
                    <p class="mt-2 text-muted">{{ __('Loading profile...') }}</p>
                </div>
                <div id="profile-content">
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <img id="profile-avatar" src="" alt="{{ __('Profile avatar') }}"
                                class="avatar-img rounded-circle mb-3" width="80" height="80"
                                onerror="this.style.display='none'; document.getElementById('profile-initial').style.display='flex';">
                            <div id="profile-initial"
                                class="avatar rounded-circle bg-brand text-white d-none align-items-center justify-content-center mx-auto mb-3"
                                style="width: 80px; height: 80px; font-size: 1.5rem; font-weight: 600;">
                            </div>
                            <span id="profile-online-status"
                                class="position-absolute bottom-0 end-0 rounded-circle border border-3 border-white"
                                style="width: 16px; height: 16px; display: none;" 
                                title="{{ __('Online') }}"
                                aria-label="{{ __('Online') }}"></span>
                        </div>
                        <h4 id="profile-name" class="mb-1"></h4>
                        <p id="profile-status" class="text-muted mb-3"></p>
                        <div id="profile-contact-status" class="mb-3">
                            <span id="contact-status-badge" class="badge bg-secondary"></span>
                        </div>
                    </div>
                    <div class="profile-info">
                        <h6 class="section-title mb-3">{{ __('Contact Information') }}</h6>
                          {{-- About Section --}}
    <div class="info-item mb-3">
        <strong><i class="bi bi-chat-left-text me-2"></i>{{ __('About:') }}</strong>
        <div class="ms-2">
            <span id="profile-about" class="text-break"></span>
            @if(auth()->id() === $headerData['userId'] ?? null)
                <button class="btn btn-sm btn-outline-primary ms-2" id="edit-about-btn">
                    <i class="bi bi-pencil" style="font-size: 0.7rem;"></i>
                </button>
            @endif
        </div>
    </div>

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
                    <div id="profile-contact-management" class="mt-4 pt-3 border-top" style="display: none;">
                        <h6 class="section-title mb-3">{{ __('Contact Management') }}</h6>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" id="profile-add-contact-btn">
                                <i class="bi bi-person-plus me-2"></i>{{ __('Add to Contacts') }}
                            </button>
                            <button class="btn btn-outline-success" id="profile-edit-contact-btn"
                                style="display: none;">
                                <i class="bi bi-pencil me-2"></i>{{ __('Edit Contact Info') }}
                            </button>
                            <button class="btn btn-outline-warning" id="profile-toggle-favorite-btn"
                                style="display: none;">
                                <i class="bi bi-star me-2"></i><span
                                    id="favorite-btn-text">{{ __('Add to Favorites') }}</span>
                            </button>
                            <button class="btn btn-outline-danger" id="profile-remove-contact-btn"
                                style="display: none;">
                                <i class="bi bi-person-dash me-2"></i>{{ __('Remove from Contacts') }}
                            </button>
                        </div>
                    </div>
                    <div class="profile-actions mt-4 pt-3 border-top">
                        <h6 class="section-title mb-3">{{ __('Quick Actions') }}</h6>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" id="start-new-chat">
                                <i class="bi bi-chat me-2"></i>{{ __('Start New Chat') }}
                            </button>
                            <button class="btn btn-outline-secondary" id="copy-profile-info">
                                <i class="bi bi-clipboard me-2"></i>{{ __('Copy Info') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add/Edit Contact Modal --}}
<div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contactModalLabel">{{ __('Add Contact') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <form id="contact-form">
                    <input type="hidden" id="contact-user-id" name="contact_user_id">
                    <input type="hidden" id="contact-id" name="contact_id">
                    <div class="mb-3">
                        <label for="contact-display-name" class="form-label">{{ __('Display Name') }}</label>
                        <input type="text" class="form-control" id="contact-display-name" name="display_name"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="contact-phone" class="form-label">{{ __('Phone') }}</label>
                        <input type="text" class="form-control" id="contact-phone" name="phone" required>
                    </div>
                    <div class="mb-3">
                        <label for="contact-note" class="form-label">{{ __('Note') }}
                            ({{ __('Optional') }})</label>
                        <textarea class="form-control" id="contact-note" name="note" rows="3"></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="contact-is-favorite" name="is_favorite">
                        <label class="form-check-label" for="contact-is-favorite">
                            {{ __('Add to favorites') }}
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary"
                    id="save-contact-btn">{{ __('Save Contact') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
    class ChatHeader {
        constructor() {
            this.modal = null;
            this.currentUser = null;
            this.contactModal = null;
            this.init();
        }

        init() {
            this.setupBackButton();
            this.setupProfileModal();
            this.setupDropdownActions();
            this.setupContactManagement();
            this.setupKeyboardNavigation();
            this.setupModalEvents();
            this.fixDropdownZIndex();
            this.setupRealTimeUpdates();
    this.setupAboutEditing(); // Add this line

        }

        setupRealTimeUpdates() {
            // Listen for real-time online status updates
            if (window.Echo) {
                const header = document.querySelector('.chat-header');
                const userId = header?.dataset.userId;
                
                if (userId) {
                    // Listen for user online status changes
                    window.Echo.private(`user.${userId}`)
                        .listen('UserOnlineStatusUpdated', (e) => {
                            this.updateOnlineStatus(e.isOnline, e.lastSeen);
                        });
                }
            }
        }

        updateOnlineStatus(isOnline, lastSeen = null) {
            const onlineIndicator = document.querySelector('.online-indicator');
            const onlineStatus = document.getElementById('online-status');
            const onlineStatusText = document.querySelector('.online-status-text');
            
            if (onlineIndicator) {
                if (isOnline) {
                    onlineIndicator.className = 'position-absolute bottom-0 end-0 bg-success rounded-circle border border-2 border-white online-indicator';
                    onlineIndicator.style.width = '12px';
                    onlineIndicator.style.height = '12px';
                    onlineIndicator.title = '{{ __("Online") }}';
                    onlineIndicator.setAttribute('aria-label', '{{ __("Online") }}');
                    onlineIndicator.dataset.online = 'true';
                } else {
                    onlineIndicator.className = 'position-absolute bottom-0 end-0 bg-secondary rounded-circle border border-2 border-white online-indicator';
                    onlineIndicator.style.width = '12px';
                    onlineIndicator.style.height = '12px';
                    const lastSeenText = lastSeen ? '{{ __("Last seen :time") }}'.replace(':time', lastSeen) : '{{ __("Offline") }}';
                    onlineIndicator.title = lastSeenText;
                    onlineIndicator.setAttribute('aria-label', lastSeenText);
                    onlineIndicator.dataset.online = 'false';
                }
            }

            if (onlineStatus && onlineStatusText) {
                if (isOnline) {
                    onlineStatus.innerHTML = '<i class="bi bi-circle-fill text-success me-1" style="font-size: 0.5rem;"></i><span class="online-status-text">{{ __("Online") }}</span>';
                } else if (lastSeen) {
                    onlineStatus.innerHTML = '<i class="bi bi-clock me-1" style="font-size: 0.5rem;"></i><span class="online-status-text">{{ __("Last seen :time") }}'.replace(':time', lastSeen) + '</span>';
                } else {
                    onlineStatus.innerHTML = '<i class="bi bi-circle me-1" style="font-size: 0.5rem;"></i><span class="online-status-text">{{ __("Offline") }}</span>';
                }
            }

            // Update profile modal if open
            const profileModal = document.getElementById('userProfileModal');
            if (profileModal && this.modal && this.modal._isShown) {
                this.updateProfileOnlineStatus(isOnline, lastSeen);
            }
        }

        updateProfileOnlineStatus(isOnline, lastSeen) {
            const profileOnlineStatus = document.getElementById('profile-online-status');
            const profileStatus = document.getElementById('profile-status');
            const profileLastSeen = document.getElementById('profile-last-seen');

            if (profileOnlineStatus) {
                if (isOnline) {
                    profileOnlineStatus.style.display = 'block';
                    profileOnlineStatus.className = 'position-absolute bottom-0 end-0 bg-success rounded-circle border border-3 border-white';
                    profileOnlineStatus.style.width = '16px';
                    profileOnlineStatus.style.height = '16px';
                    if (profileStatus) profileStatus.textContent = '{{ __("Online") }}';
                } else {
                    profileOnlineStatus.style.display = 'block';
                    profileOnlineStatus.className = 'position-absolute bottom-0 end-0 bg-secondary rounded-circle border border-3 border-white';
                    profileOnlineStatus.style.width = '16px';
                    profileOnlineStatus.style.height = '16px';
                    if (profileStatus) profileStatus.textContent = '{{ __("Offline") }}';
                }
            }

            if (profileLastSeen && lastSeen) {
                profileLastSeen.textContent = lastSeen;
                profileLastSeen.className = 'ms-2';
            }
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

            // Block and report user actions
            const blockBtn = document.getElementById('block-user-btn');
            if (blockBtn) {
                blockBtn.addEventListener('click', () => {
                    this.blockUser();
                });
            }
            const reportBtn = document.getElementById('report-user-btn');
            if (reportBtn) {
                reportBtn.addEventListener('click', () => {
                    this.reportUser();
                });
            }
        }

        setupContactManagement() {
            // Add contact
            document.getElementById('add-contact-btn')?.addEventListener('click', (e) => {
                this.handleAddContact(e.target.closest('#add-contact-btn'));
            });

            // Edit contact
            document.getElementById('edit-contact-btn')?.addEventListener('click', (e) => {
                this.handleEditContact(e.target.closest('#edit-contact-btn'));
            });

            // Remove contact
            document.getElementById('remove-contact-btn')?.addEventListener('click', (e) => {
                this.handleRemoveContact(e.target.closest('#remove-contact-btn'));
            });

            // Favorite actions
            document.getElementById('add-favorite-btn')?.addEventListener('click', (e) => {
                this.handleToggleFavorite(e.target.closest('#add-favorite-btn'), true);
            });

            document.getElementById('remove-favorite-btn')?.addEventListener('click', (e) => {
                this.handleToggleFavorite(e.target.closest('#remove-favorite-btn'), false);
            });

            // Contact modal
            document.getElementById('save-contact-btn')?.addEventListener('click', () => {
                this.saveContact();
            });

            // Profile modal contact actions
            document.getElementById('profile-add-contact-btn')?.addEventListener('click', () => {
                this.handleProfileAddContact();
            });

            document.getElementById('profile-edit-contact-btn')?.addEventListener('click', () => {
                this.handleProfileEditContact();
            });

            document.getElementById('profile-remove-contact-btn')?.addEventListener('click', () => {
                this.handleProfileRemoveContact();
            });

            document.getElementById('profile-toggle-favorite-btn')?.addEventListener('click', () => {
                this.handleProfileToggleFavorite();
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

            const contactModal = document.getElementById('contactModal');
            if (contactModal) {
                this.contactModal = new bootstrap.Modal(contactModal);
                contactModal.addEventListener('hidden.bs.modal', () => {
                    this.cleanupContactModal();
                });
            }
        }

        handleKeyboardNavigation(e) {
            if (e.key === 'Escape') {
                this.handleEscapeKey();
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
        }
// Add these methods to your ChatHeader class
setupAboutEditing() {
    // Edit about button
    document.getElementById('edit-about-btn')?.addEventListener('click', () => {
        this.showEditAboutForm();
    });

    // Cancel edit
    document.getElementById('cancel-edit-about')?.addEventListener('click', () => {
        this.hideEditAboutForm();
    });

    // Save about
    document.getElementById('save-about')?.addEventListener('click', () => {
        this.saveAbout();
    });

    // Character count
    document.getElementById('about-input')?.addEventListener('input', (e) => {
        this.updateAboutCharCount(e.target.value.length);
    });
}

showEditAboutForm() {
    const aboutText = document.getElementById('profile-about').textContent;
    const editForm = document.getElementById('edit-about-form');
    const aboutDisplay = document.getElementById('profile-about').parentElement;
    
    document.getElementById('about-input').value = aboutText;
    this.updateAboutCharCount(aboutText.length);
    
    aboutDisplay.style.display = 'none';
    editForm.style.display = 'block';
    document.getElementById('about-input').focus();
}

hideEditAboutForm() {
    const editForm = document.getElementById('edit-about-form');
    const aboutDisplay = document.getElementById('profile-about').parentElement;
    
    aboutDisplay.style.display = 'block';
    editForm.style.display = 'none';
}

updateAboutCharCount(length) {
    const charCount = document.getElementById('about-char-count');
    if (charCount) {
        charCount.textContent = `${length}/140`;
        charCount.className = length >= 140 ? 'text-danger' : 'text-muted';
    }
}

        /**
         * Block the current chat user. Prompts for confirmation and sends a POST
         * request to the API. Uses CSRF token embedded in the page. Shows a toast
         * message on success or failure.
         */
        async blockUser() {
            const chatHeaderEl = document.querySelector('.chat-header');
            const userId = chatHeaderEl?.dataset.userId;
            if (!userId) return;
            if (!confirm('Are you sure you want to block this user?')) return;
            try {
                const response = await fetch(`/api/v1/users/${userId}/block`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                });
                if (response.ok) {
                    this.showToast('User blocked successfully', 'success');
                } else {
                    const data = await response.json().catch(() => ({}));
                    this.showToast(data.error || 'Failed to block user', 'error');
                }
            } catch (error) {
                console.error(error);
                this.showToast('Failed to block user', 'error');
            }
        }

        /**
         * Report the current chat user. Prompts for a reason and optional details,
         * and optionally offers to block the user. Sends a POST request to the API.
         */
        async reportUser() {
            const chatHeaderEl = document.querySelector('.chat-header');
            const userId = chatHeaderEl?.dataset.userId;
            if (!userId) return;
            const reason = prompt('Please provide a reason for reporting this user (e.g. spam, abuse):');
            if (!reason) return;
            const details = prompt('Additional details (optional):') || '';
            const blockAlso = confirm('Do you also want to block this user?');
            const payload = {
                reason: reason,
                details: details,
                block: blockAlso
            };
            try {
                const response = await fetch(`/api/v1/users/${userId}/report`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                if (response.ok) {
                    this.showToast('Report submitted', 'success');
                } else {
                    const data = await response.json().catch(() => ({}));
                    this.showToast(data.error || 'Failed to submit report', 'error');
                }
            } catch (error) {
                console.error(error);
                this.showToast('Failed to submit report', 'error');
            }
        }

async saveAbout() {
    const aboutText = document.getElementById('about-input').value.trim();
    
    if (aboutText.length > 140) {
        this.showToast('About text cannot exceed 140 characters', 'error');
        return;
    }

    try {
        const response = await fetch('/api/v1/user/about', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ about: aboutText })
        });

        const result = await response.json();

        if (result.success) {
            document.getElementById('profile-about').textContent = aboutText || 'Hey there! I am using GekyChat';
            this.hideEditAboutForm();
            this.showToast('About updated successfully', 'success');
        } else {
            throw new Error(result.message || 'Failed to update about');
        }
    } catch (error) {
        this.showToast('Failed to update about', 'error');
    }
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
                lastSeen: button.dataset.userLastSeen,
                phone: button.dataset.userPhone,
                createdAt: button.dataset.userCreatedAt,
                isContact: button.dataset.isContact === '1',
                contactId: button.dataset.contactId || null
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

                // Fetch fresh contact data from API
                try {
                    const response = await fetch(`/api/v1/contacts/user/${user.id}/profile`);
                    if (response.ok) {
                        const data = await response.json();
                        if (data.success) {
                            user = { ...user, ...data.user };
                        }
                    }
                } catch (error) {
                    console.warn('Failed to fetch fresh profile data, using cached data');
                }

                this.updateProfileModal(user);
                this.showLoadingState(false);

            } catch (error) {
                console.error('Profile loading error:', error);
                this.showLoadingState(false);
                this.handleProfileError(error);
            }

            this.modal = new bootstrap.Modal(document.getElementById('userProfileModal'));
            this.modal.show();
        }

        updateProfileModal(user) {
            this.setBasicProfileInfo(user);
            this.updateContactInfo(user);
            this.updateContactManagement(user);
            this.setupSimpleActions(user);
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

            this.updateProfileOnlineStatus(user.online, user.lastSeen);
        }

        updateContactInfo(user) {
             const aboutElement = document.getElementById('profile-about');
            const phoneElement = document.getElementById('profile-phone');
            const lastSeenElement = document.getElementById('profile-last-seen');
            const memberSinceElement = document.getElementById('profile-member-since');
  // About
    if (aboutElement) {
        aboutElement.textContent = user.about || 'Hey there! I am using GekyChat';
    }
            // Phone
            if (user.phone) {
                phoneElement.textContent = user.phone;
                phoneElement.className = 'ms-2';
            } else {
                phoneElement.textContent = '{{ __("Not available") }}';
                phoneElement.className = 'ms-2 text-muted';
            }

            // Last Seen
            if (user.lastSeen) {
                lastSeenElement.textContent = user.lastSeen;
                lastSeenElement.className = 'ms-2';
            } else {
                lastSeenElement.textContent = '{{ __("Unknown") }}';
                lastSeenElement.className = 'ms-2 text-muted';
            }

            // Member Since
            if (user.createdAt) {
                memberSinceElement.textContent = user.createdAt;
                memberSinceElement.className = 'ms-2';
            } else {
                memberSinceElement.textContent = '{{ __("Unknown") }}';
                memberSinceElement.className = 'ms-2 text-muted';
            }
        }

        updateContactManagement(user) {
            const contactManagement = document.getElementById('profile-contact-management');
            const contactStatusBadge = document.getElementById('contact-status-badge');
            const isContact = user.contactData?.is_contact || user.isContact;

            if (user.id === {{ auth()->id() }}) {
                contactManagement.style.display = 'none';
                contactStatusBadge.style.display = 'none';
                return;
            }

            contactManagement.style.display = 'block';

            if (isContact) {
                contactStatusBadge.textContent = '{{ __("In your contacts") }}';
                contactStatusBadge.className = 'badge bg-success';

                document.getElementById('profile-add-contact-btn').style.display = 'none';
                document.getElementById('profile-edit-contact-btn').style.display = 'block';
                document.getElementById('profile-remove-contact-btn').style.display = 'block';
                document.getElementById('profile-toggle-favorite-btn').style.display = 'block';

                const isFavorite = user.contactData?.is_favorite;
                const favoriteBtn = document.getElementById('profile-toggle-favorite-btn');
                const favoriteText = document.getElementById('favorite-btn-text');

                if (isFavorite) {
                    favoriteText.textContent = '{{ __("Remove from Favorites") }}';
                    favoriteBtn.classList.remove('btn-outline-warning');
                    favoriteBtn.classList.add('btn-warning');
                } else {
                    favoriteText.textContent = '{{ __("Add to Favorites") }}';
                    favoriteBtn.classList.remove('btn-warning');
                    favoriteBtn.classList.add('btn-outline-warning');
                }

                favoriteBtn.dataset.contactId = user.contactData?.contact_data?.id || user.contactId;
                document.getElementById('profile-edit-contact-btn').dataset.contactId = user.contactData?.contact_data?.id || user.contactId;
                document.getElementById('profile-remove-contact-btn').dataset.contactId = user.contactData?.contact_data?.id || user.contactId;

            } else {
                contactStatusBadge.textContent = '{{ __("Not in contacts") }}';
                contactStatusBadge.className = 'badge bg-secondary';

                document.getElementById('profile-add-contact-btn').style.display = 'block';
                document.getElementById('profile-edit-contact-btn').style.display = 'none';
                document.getElementById('profile-remove-contact-btn').style.display = 'none';
                document.getElementById('profile-toggle-favorite-btn').style.display = 'none';

                document.getElementById('profile-add-contact-btn').dataset.userId = user.id;
                document.getElementById('profile-add-contact-btn').dataset.userName = user.name;
                document.getElementById('profile-add-contact-btn').dataset.userPhone = user.phone;
            }
        }

        setupSimpleActions(user) {
            document.getElementById('start-new-chat').onclick = () => {
                if (user.id) {
                    window.location.href = `/c/start/${user.id}`;
                } else {
                    this.showToast('{{ __("Cannot start chat with this user") }}', 'error');
                }
            };

            document.getElementById('copy-profile-info').onclick = () => {
                const profileInfo = `{{ __("Name") }}: ${user.name}
{{ __("Phone") }}: ${user.phone || '{{ __("Not available") }}'}
{{ __("Last Seen") }}: ${user.lastSeen || '{{ __("Unknown") }}'}
{{ __("Member Since") }}: ${user.createdAt || '{{ __("Unknown") }}'}`.trim();

                this.copyToClipboard(profileInfo);
            };
        }

        // Contact Management Methods
        async handleAddContact(button) {
            const userId = button.dataset.userId;
            const userName = button.dataset.userName;
            const userPhone = button.dataset.userPhone;

            this.showContactModal('add', {
                contact_user_id: userId,
                display_name: userName,
                phone: userPhone
            });
        }

        async handleEditContact(button) {
            const contactId = button.dataset.contactId;

            try {
                const response = await fetch(`/api/v1/contacts/${contactId}`);
                if (response.ok) {
                    const data = await response.json();
                    this.showContactModal('edit', data.data);
                } else {
                    throw new Error('Failed to fetch contact data');
                }
            } catch (error) {
                this.showToast('{{ __("Failed to load contact data") }}', 'error');
            }
        }

        async handleRemoveContact(button) {
            const contactId = button.dataset.contactId;

            if (confirm('{{ __("Are you sure you want to remove this contact?") }}')) {
                try {
                    const response = await fetch(`/api/v1/contacts/${contactId}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });

                    if (response.ok) {
                        this.showToast('{{ __("Contact removed successfully") }}', 'success');
                        this.refreshContactStatus();
                        this.closeDropdown(button);
                    } else {
                        throw new Error('Failed to remove contact');
                    }
                } catch (error) {
                    this.showToast('{{ __("Failed to remove contact") }}', 'error');
                }
            }
        }

        async handleToggleFavorite(button, isFavorite) {
            const contactId = button.dataset.contactId;
            const url = `/api/v1/contacts/${contactId}/favorite`;
            const method = isFavorite ? 'POST' : 'DELETE';

            try {
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                if (response.ok) {
                    const message = isFavorite ? '{{ __("Contact added to favorites") }}' : '{{ __("Contact removed from favorites") }}';
                    this.showToast(message, 'success');
                    this.refreshContactStatus();
                    this.closeDropdown(button);
                } else {
                    throw new Error('Failed to update favorite status');
                }
            } catch (error) {
                this.showToast('{{ __("Failed to update favorite status") }}', 'error');
            }
        }

        showContactModal(mode, data = {}) {
            const modal = document.getElementById('contactModal');
            const form = document.getElementById('contact-form');
            const title = document.getElementById('contactModalLabel');

            if (mode === 'add') {
                title.textContent = '{{ __("Add Contact") }}';
                form.reset();
                document.getElementById('contact-user-id').value = data.contact_user_id || '';
                document.getElementById('contact-id').value = '';
                document.getElementById('contact-display-name').value = data.display_name || '';
                document.getElementById('contact-phone').value = data.phone || '';
                document.getElementById('contact-note').value = '';
                document.getElementById('contact-is-favorite').checked = false;
            } else {
                title.textContent = '{{ __("Edit Contact") }}';
                document.getElementById('contact-user-id').value = data.user_id || '';
                document.getElementById('contact-id').value = data.id || '';
                document.getElementById('contact-display-name').value = data.display_name || '';
                document.getElementById('contact-phone').value = data.phone || '';
                document.getElementById('contact-note').value = data.note || '';
                document.getElementById('contact-is-favorite').checked = data.is_favorite || false;
            }

            this.contactModal.show();
        }

        async saveContact() {
            const form = document.getElementById('contact-form');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);

            const isEdit = !!data.contact_id;
            const url = isEdit ? `/api/v1/contacts/${data.contact_id}` : '/api/v1/contacts';
            const method = isEdit ? 'PUT' : 'POST';

            try {
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    const message = isEdit ? '{{ __("Contact updated successfully") }}' : '{{ __("Contact added successfully") }}';
                    this.showToast(message, 'success');
                    this.contactModal.hide();
                    this.refreshContactStatus();

                    if (this.modal && this.modal._isShown) {
                        const currentUser = this.currentUser;
                        if (currentUser) {
                            this.updateProfileModal(currentUser);
                        }
                    }
                } else {
                    throw new Error(result.message || 'Failed to save contact');
                }
            } catch (error) {
                this.showToast(error.message || '{{ __("Failed to save contact") }}', 'error');
            }
        }

        // Profile modal contact actions
        handleProfileAddContact() {
            const button = document.getElementById('profile-add-contact-btn');
            this.handleAddContact(button);
        }

        handleProfileEditContact() {
            const button = document.getElementById('profile-edit-contact-btn');
            this.handleEditContact(button);
        }

        handleProfileRemoveContact() {
            const button = document.getElementById('profile-remove-contact-btn');
            this.handleRemoveContact(button);
        }

        handleProfileToggleFavorite() {
            const button = document.getElementById('profile-toggle-favorite-btn');
            const isCurrentlyFavorite = button.classList.contains('btn-warning');
            this.handleToggleFavorite(button, !isCurrentlyFavorite);
        }

        async refreshContactStatus() {
            const header = document.querySelector('.chat-header');
            const userId = header?.dataset.userId;

            if (userId) {
                try {
                    const response = await fetch(`/api/v1/contacts/user/${userId}/profile`);
                    if (response.ok) {
                        const data = await response.json();
                        if (data.success) {
                            header.dataset.isContact = data.user.is_contact ? '1' : '0';
                            header.dataset.contactId = data.user.contact_data?.id || '';

                            const badge = document.getElementById('contact-status-badge');
                            if (badge) {
                                if (data.user.is_contact) {
                                    badge.innerHTML = '<i class="bi bi-person-check-fill text-success me-1" style="font-size: 0.5rem;"></i><span>{{ __("In your contacts") }}</span>';
                                } else {
                                    badge.innerHTML = '<i class="bi bi-person-plus text-muted me-1" style="font-size: 0.5rem;"></i><span>{{ __("Not in contacts") }}</span>';
                                }
                            }
                        }
                    }
                } catch (error) {
                    console.error('Failed to refresh contact status:', error);
                }
            }
        }

        showFormError(message, containerId = 'form-errors') {
            const errorsContainer = document.getElementById(containerId);
            if (errorsContainer) {
                errorsContainer.textContent = message;
                errorsContainer.classList.remove('d-none');
            }
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
        }

        cleanupContactModal() {
            const form = document.getElementById('contact-form');
            form.reset();
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

    .typing-dots .dot:nth-child(1) {
        animation-delay: -0.32s;
    }

    .typing-dots .dot:nth-child(2) {
        animation-delay: -0.16s;
    }

    .typing-dots .dot:nth-child(3) {
        animation-delay: 0s;
    }

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

    .contact-status-badge {
        font-size: 0.75rem;
    }

    .online-indicator {
        transition: all 0.3s ease;
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

    .modal.fade .modal-dialog {
        transform: scale(0.9);
        transition: transform 0.2s ease-out;
    }

    .modal.show .modal-dialog {
        transform: scale(1);
    }
</style>