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
    'userId' => null
  ];
  
  $hasUserId = isset($headerData['userId']) && !empty($headerData['userId']);
@endphp

<header class="chat-header p-3 border-bottom d-flex align-items-center" 
        role="banner" 
        data-context="direct"
        data-user-id="{{ $hasUserId ? $headerData['userId'] : '' }}">
  
  {{-- Back Button (Mobile) --}}
  <button class="btn btn-sm btn-ghost d-md-none me-2" 
          id="back-to-conversations" 
          aria-label="{{ __('Back to conversations') }}" 
          title="{{ __('Back to conversations') }}">
    <i class="bi bi-arrow-left" aria-hidden="true"></i>
  </button>

  {{-- User Info Section --}}
  <div class="d-flex align-items-center flex-grow-1 min-width-0">
    
    {{-- Avatar with Status --}}
    <div class="position-relative">
      @if(!empty($headerData['avatar']))
        <img src="{{ $headerData['avatar'] }}" 
             alt="{{ __(':name avatar', ['name' => $headerData['name']]) }}"
             class="avatar avatar-img me-3" 
             loading="lazy" 
             width="40" 
             height="40"
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
      @if($headerData['online'])
        <span class="position-absolute bottom-0 end-0 bg-success rounded-circle border border-2 border-white"
              style="width: 12px; height: 12px;" 
              title="{{ __('Online') }}" 
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
          @if($headerData['online'])
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
    <div id="online-list" class="d-none d-md-flex align-items-center gap-2" 
         aria-label="{{ __('Online users') }}"></div>
    
    {{-- Options Menu --}}
    <div class="dropdown chat-options-dropdown" id="chat-options-dropdown">
      <button class="btn btn-sm btn-ghost" 
              data-bs-toggle="dropdown" 
              aria-expanded="false" 
              aria-label="{{ __('Chat options') }}" 
              title="{{ __('Chat options') }}"
              id="chat-options-button">
        <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end" role="menu">
        <li role="none">
          @if($hasUserId)
            <button class="dropdown-item d-flex align-items-center gap-2 view-profile-btn"
                    data-user-id="{{ $headerData['userId'] }}"
                    data-user-name="{{ $headerData['name'] }}"
                    data-user-avatar="{{ $headerData['avatar'] ?? '' }}"
                    data-user-initial="{{ $headerData['initial'] }}"
                    data-user-online="{{ $headerData['online'] ? '1' : '0' }}"
                    data-user-last-seen="{{ $headerData['lastSeen'] ? $headerData['lastSeen']->diffForHumans() : '' }}"
                    role="menuitem"
                    tabindex="0">
              <i class="bi bi-person" aria-hidden="true"></i>
              <span>{{ __('View profile') }}</span>
            </button>
          @else
            <span class="dropdown-item d-flex align-items-center gap-2 text-muted" 
                  role="menuitem" 
                  aria-disabled="true">
              <i class="bi bi-person" aria-hidden="true"></i>
              <span>{{ __('View profile') }}</span>
            </span>
          @endif
        </li>
        <li role="none">
          <button class="dropdown-item d-flex align-items-center gap-2" 
                  id="mute-chat-btn" 
                  role="menuitem"
                  tabindex="0">
            <i class="bi bi-bell" aria-hidden="true"></i>
            <span>{{ __('Mute notifications') }}</span>
          </button>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li role="none">
          <button class="dropdown-item d-flex align-items-center gap-2 text-danger" 
                  id="clear-chat-btn" 
                  role="menuitem"
                  tabindex="0">
            <i class="bi bi-trash" aria-hidden="true"></i>
            <span>{{ __('Clear chat') }}</span>
          </button>
        </li>
      </ul>
    </div>
  </div>
</header>

{{-- Profile Modal --}}
<div class="modal fade" id="userProfileModal" tabindex="-1" 
     aria-labelledby="userProfileModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="true">
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
              <div id="profile-initial" class="avatar rounded-circle bg-brand text-white d-none align-items-center justify-content-center mx-auto mb-3" 
                   style="width: 80px; height: 80px; font-size: 1.5rem; font-weight: 600;">
              </div>
              
              {{-- Online Status --}}
              <span id="profile-online-status" class="position-absolute bottom-0 end-0 rounded-circle border border-3 border-white"
                    style="width: 16px; height: 16px; display: none;" 
                    title="{{ __('Online') }}"></span>
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

          {{-- Contact Actions --}}
          <div class="profile-actions mt-4 pt-3 border-top">
            <h6 class="section-title mb-3">{{ __('Actions') }}</h6>
            <div class="d-grid gap-2">
              
              {{-- Save to Contacts --}}
              <div id="save-to-contacts-section" style="display: none;">
                <button class="btn btn-success mb-2" id="save-to-contacts-btn">
                  <i class="bi bi-person-plus me-2"></i>{{ __('Save to Contacts') }}
                </button>
                <small class="text-muted d-block text-center">
                  <i class="bi bi-info-circle me-1"></i>{{ __('This person is not in your contacts') }}
                </small>
              </div>
              
              {{-- Action Buttons --}}
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

{{-- Add Contact Modal --}}
<div class="modal fade" id="addContactModal" tabindex="-1" 
     aria-labelledby="addContactModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addContactModalLabel">{{ __('Add to Contacts') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" 
                aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body">
        <form id="addContactForm">
          <div id="form-errors"></div>
          <div class="mb-3">
            <label class="form-label">{{ __('Name') }}</label>
            <input type="text" class="form-control" name="display_name" required
                   placeholder="{{ __('Enter contact name') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Phone') }}</label>
            <input type="text" class="form-control" id="contact-phone" disabled>
            <small class="text-muted">{{ __('Phone number cannot be changed') }}</small>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Note') }} ({{ __('Optional') }})</label>
            <textarea class="form-control" name="note" rows="3" 
                      placeholder="{{ __('Add a note about this contact...') }}"></textarea>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_favorite" id="isFavorite">
            <label class="form-check-label" for="isFavorite">
              {{ __('Add to favorites') }}
            </label>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          {{ __('Cancel') }}
        </button>
        <button type="button" class="btn btn-primary" id="saveContactBtn">
          {{ __('Save Contact') }}
        </button>
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
    // Ensure dropdowns have proper z-index
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
    // Mute chat functionality
    document.getElementById('mute-chat-btn')?.addEventListener('click', () => {
      this.toggleMuteChat();
    });

    // Clear chat functionality
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
    // Escape key closes modals and dropdowns
    if (e.key === 'Escape') {
      this.handleEscapeKey();
    }

    // Enter key in dropdown
    if (e.key === 'Enter' && e.target.closest('.dropdown-item')) {
      e.target.click();
    }

    // Arrow navigation in dropdown
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      this.handleDropdownNavigation(e);
    }
  }

  handleEscapeKey() {
    // Close open modals
    const modals = document.querySelectorAll('.modal.show');
    if (modals.length > 0) {
      const bootstrap = window.bootstrap;
      modals.forEach(modal => {
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) bsModal.hide();
      });
      return;
    }

    // Close dropdowns
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
    
    // Set basic info immediately
    this.setBasicProfileInfo(user);
    
    try {
      // Load additional data
      const userDetails = await this.loadUserProfileData(user.id);
      this.updateProfileModal(userDetails);
    } catch (error) {
      this.handleProfileError(error);
    } finally {
      this.showLoadingState(false);
    }

    // Show modal
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
    const response = await fetch(`/api/v1/users/${userId}/profile`);
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const data = await response.json();
    
    if (!data.success) {
      throw new Error(data.message || '{{ __("Failed to load profile") }}');
    }
    
    return data.user;
  }

  updateProfileModal(userDetails) {
    // Update contact information
    document.getElementById('profile-phone').textContent = 
      userDetails.phone || '{{ __("Not available") }}';
    document.getElementById('profile-member-since').textContent = 
      userDetails.member_since || '{{ __("Unknown") }}';

    // Setup action buttons
    this.setupProfileActions(userDetails);
  }

  setupProfileActions(userDetails) {
    // Save to contacts
    const saveSection = document.getElementById('save-to-contacts-section');
    if (!userDetails.is_contact) {
      saveSection.style.display = 'block';
      document.getElementById('save-to-contacts-btn').onclick = () => {
        this.saveToContacts(userDetails.id, userDetails.name, userDetails.phone);
      };
    } else {
      saveSection.style.display = 'none';
    }

    // Other actions
    document.getElementById('view-full-profile').onclick = () => {
      window.open(`/users/${userDetails.id}`, '_blank');
    };

    document.getElementById('view-contact-info').onclick = () => {
      this.showContactDetails(userDetails);
    };

    document.getElementById('block-user').onclick = () => {
      this.blockUser(userDetails.id, userDetails.name);
    };
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

  saveToContacts(userId, userName, userPhone) {
    const modalHtml = `
      <div class="modal fade" id="addContactModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">{{ __("Add to Contacts") }}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <form id="addContactForm">
                <div class="mb-3">
                  <label class="form-label">{{ __("Name") }}</label>
                  <input type="text" class="form-control" name="display_name" value="${userName}" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">{{ __("Phone") }}</label>
                  <input type="text" class="form-control" value="${userPhone}" disabled>
                  <small class="text-muted">{{ __("Phone number cannot be changed") }}</small>
                </div>
                <div class="mb-3">
                  <label class="form-label">{{ __("Note") }} ({{ __("Optional") }})</label>
                  <textarea class="form-control" name="note" rows="3" placeholder="{{ __("Add a note about this contact...") }}"></textarea>
                </div>
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" name="is_favorite" id="isFavorite">
                  <label class="form-check-label" for="isFavorite">
                    {{ __("Add to favorites") }}
                  </label>
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __("Cancel") }}</button>
              <button type="button" class="btn btn-primary" id="saveContactBtn">{{ __("Save Contact") }}</button>
            </div>
          </div>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('addContactModal'));
    
    document.getElementById('saveContactBtn').onclick = () => {
      this.saveContactToServer(userId, userPhone, modal);
    };

    modal.show();
    
    document.getElementById('addContactModal').addEventListener('hidden.bs.modal', function () {
      this.remove();
    });
  }

  async saveContactToServer(userId, userPhone, modal) {
    const form = document.getElementById('addContactForm');
    const formData = new FormData(form);
    
    const contactData = {
      display_name: formData.get('display_name'),
      phone: userPhone,
      contact_user_id: userId,
      note: formData.get('note'),
      is_favorite: formData.get('is_favorite') === 'on'
    };

    try {
      const response = await fetch('/api/v1/contacts', {
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
        this.updateContactUI();
      } else {
        this.showToast(`{{ __("Failed to save contact:") }} ${data.message || '{{ __("Unknown error") }}'}`, 'error');
      }
    } catch (error) {
      console.error('Error saving contact:', error);
      this.showToast('{{ __("Failed to save contact") }}', 'error');
    }
  }

  updateContactUI() {
    const saveSection = document.getElementById('save-to-contacts-section');
    saveSection.innerHTML = `
      <div class="alert alert-success mb-2">
        <i class="bi bi-check-circle me-2"></i>
        {{ __("Saved to contacts") }}
      </div>
    `;
  }

  showContactDetails(user) {
    alert(`{{ __("Contact:") }} ${user.name}\n{{ __("Phone:") }} ${user.phone || '{{ __("Not available") }}'}`);
  }

  blockUser(userId, userName) {
    if (confirm(`{{ __("Are you sure you want to block :name?", ['name' => '${userName}']) }}`)) {
      // Implement block functionality
      console.log(`Blocking user: ${userId}`);
    }
  }

  toggleMuteChat() {
    // Implement mute functionality
    this.showToast('{{ __("Chat notifications muted") }}', 'info');
  }

  clearChat() {
    if (confirm('{{ __("Are you sure you want to clear this chat? This action cannot be undone.") }}')) {
      // Implement clear chat functionality
      this.showToast('{{ __("Chat cleared") }}', 'info');
    }
  }

  showToast(message, type = 'info') {
    // Implement toast notification
    console.log(`Toast [${type}]: ${message}`);
  }

  cleanupProfileModal() {
    this.currentUser = null;
    this.isLoading = false;
  }
}

// Initialize chat header when DOM is loaded
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
  z-index: 1030; /* Higher than chat messages */
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

/* Fix dropdown z-index issues */
.chat-options-dropdown {
  position: relative;
  z-index: 1060; /* Higher than header */
}

.chat-options-dropdown .dropdown-menu {
  z-index: 1060 !important; /* Force higher than chat messages */
  position: absolute;
  top: 100%;
  right: 0;
  left: auto;
  margin-top: 0.125rem;
}

/* Profile Modal Styles */
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

.profile-actions .btn {
  border-radius: 8px;
}

/* Focus styles for keyboard navigation */
.dropdown-item:focus,
.btn:focus {
  outline: 2px solid var(--primary);
  outline-offset: 2px;
}

.modal .btn:focus {
  box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Ensure proper stacking context */
.chat-container {
  position: relative;
  z-index: 1;
}

.chat-messages {
  position: relative;
  z-index: 1;
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
</style>