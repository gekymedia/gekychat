{{-- resources/views/chat/shared/forward_modal.blade.php --}}
@php
  $context = $context ?? 'direct'; // 'direct' or 'group'
  $isGroup = $context === 'group';
@endphp

<div class="modal fade" id="forward-modal" tabindex="-1" aria-labelledby="forwardModalLabel" 
     aria-hidden="true" data-bs-backdrop="static" data-context="{{ $context }}">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="forwardModalLabel">Forward Message</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body">
        <input type="hidden" id="forward-source-id" value="">

        {{-- Search Bar --}}
        <div class="mb-3">
          <div class="input-group input-group-lg">
            <span class="input-group-text bg-light border-end-0">
              <i class="bi bi-search text-muted" aria-hidden="true"></i>
            </span>
            <input type="text" class="form-control border-start-0" id="forward-search" 
                   placeholder="Search conversations and groups..." 
                   aria-label="Search conversations and groups">
          </div>
        </div>

        {{-- Tabs for different target types --}}
        <ul class="nav nav-tabs mb-3" id="forwardTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-all" data-bs-toggle="tab" data-bs-target="#fwd-all" 
                    type="button" role="tab" aria-controls="fwd-all" aria-selected="true">
              <i class="bi bi-grid me-1" aria-hidden="true"></i>
              All
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-dms" data-bs-toggle="tab" data-bs-target="#fwd-dms" 
                    type="button" role="tab" aria-controls="fwd-dms" aria-selected="false">
              <i class="bi bi-chat me-1" aria-hidden="true"></i>
              Chats
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-groups" data-bs-toggle="tab" data-bs-target="#fwd-groups" 
                    type="button" role="tab" aria-controls="fwd-groups" aria-selected="false">
              <i class="bi bi-people me-1" aria-hidden="true"></i>
              Groups
            </button>
          </li>
        </ul>

        <div class="tab-content" id="forwardTabsContent">
          {{-- All Tab --}}
          <div class="tab-pane fade show active" id="fwd-all" role="tabpanel" aria-labelledby="tab-all">
            <div class="forward-list-container" style="max-height: 400px; overflow-y: auto;">
              {{-- Recent Chats Section --}}
              <div class="forward-section mb-3">
                <div class="px-2 py-2 text-muted small fw-bold">RECENT CHATS</div>
                <div id="forward-recent-list" class="list-group list-group-flush" role="listbox" 
                     aria-label="Recent chats"></div>
              </div>

              {{-- Contacts Section --}}
              <div class="forward-section mb-3">
                <div class="px-2 py-2 text-muted small fw-bold">CONTACTS</div>
                <div id="forward-contacts-list" class="list-group list-group-flush" role="listbox" 
                     aria-label="Contacts"></div>
              </div>

              {{-- Groups Section --}}
              <div class="forward-section">
                <div class="px-2 py-2 text-muted small fw-bold">GROUPS</div>
                <div id="forward-groups-list" class="list-group list-group-flush" role="listbox" 
                     aria-label="Groups"></div>
              </div>
            </div>
          </div>

          {{-- Direct Messages Tab --}}
          <div class="tab-pane fade" id="fwd-dms" role="tabpanel" aria-labelledby="tab-dms">
            <div class="forward-list-container" style="max-height: 400px; overflow-y: auto;">
              <div id="forward-dm-list" class="list-group list-group-flush" role="listbox" 
                   aria-label="Available chats"></div>
            </div>
          </div>

          {{-- Groups Tab --}}
          <div class="tab-pane fade" id="fwd-groups" role="tabpanel" aria-labelledby="tab-groups">
            <div class="forward-list-container" style="max-height: 400px; overflow-y: auto;">
              <div id="forward-group-list" class="list-group list-group-flush" role="listbox" 
                   aria-label="Available groups"></div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="modal-footer">
        <div class="me-auto">
          <span class="text-muted">
            <span id="forward-selected-count">0</span> selected
          </span>
        </div>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="forward-confirm" disabled>
          <i class="bi bi-forward me-1" aria-hidden="true"></i>
          Forward to <span id="forward-count">0</span>
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Inline Styles for Forward Modal --}}
<style>
#forward-modal .modal-content {
  border-radius: 12px;
  border: none;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.forward-list-container {
  scrollbar-width: thin;
  scrollbar-color: var(--border) transparent;
}

.forward-list-container::-webkit-scrollbar {
  width: 6px;
}

.forward-list-container::-webkit-scrollbar-track {
  background: transparent;
}

.forward-list-container::-webkit-scrollbar-thumb {
  background: var(--border);
  border-radius: 3px;
}

.forward-list-container::-webkit-scrollbar-thumb:hover {
  background: var(--wa-muted);
}

.forward-section {
  border-radius: 8px;
  margin-bottom: 1rem;
}

.forward-section:last-child {
  margin-bottom: 0;
}

.list-group-item {
  border: none;
  border-radius: 8px;
  margin-bottom: 2px;
  transition: all 0.2s ease;
  padding: 12px 16px;
}

.list-group-item:hover {
  background: color-mix(in srgb, var(--wa-green) 5%, transparent);
  transform: translateX(2px);
}

.list-group-item.selected {
  background: color-mix(in srgb, var(--wa-green) 10%, transparent);
  border-left: 3px solid var(--wa-green);
}

[data-context="group"] .list-group-item.selected {
  background: color-mix(in srgb, var(--group-accent) 10%, transparent);
  border-left: 3px solid var(--group-accent);
}

.form-check-input {
  margin-right: 12px;
}

.avatar-placeholder {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.875rem;
  margin-right: 12px;
  background: color-mix(in srgb, var(--wa-green) 15%, transparent);
  color: var(--wa-green);
}

.avatar-img {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  margin-right: 12px;
  border: 1px solid var(--border);
}

.contact-info {
  flex: 1;
  min-width: 0;
}

.contact-name {
  font-weight: 600;
  margin-bottom: 2px;
}

.contact-subtitle {
  font-size: 0.875rem;
  color: var(--wa-muted);
  text-overflow: ellipsis;
  overflow: hidden;
  white-space: nowrap;
}

.nav-tabs {
  border-bottom: 1px solid var(--border);
}

.nav-tabs .nav-link {
  border: none;
  padding: 8px 16px;
  font-weight: 500;
  color: var(--wa-muted);
  transition: all 0.2s ease;
  background: transparent;
}

.nav-tabs .nav-link.active {
  color: var(--wa-green);
  border-bottom: 2px solid var(--wa-green);
  background: transparent;
}

[data-context="group"] .nav-tabs .nav-link.active {
  color: var(--group-accent);
  border-bottom: 2px solid var(--group-accent);
}

.nav-tabs .nav-link:hover {
  color: var(--text);
  border-bottom-color: transparent;
}

.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  color: var(--wa-muted);
}

.empty-state i {
  font-size: 3rem;
  margin-bottom: 1rem;
  opacity: 0.5;
}

/* Responsive Design */
@media (max-width: 768px) {
  #forward-modal .modal-dialog {
    margin: 0.5rem;
  }
  
  #forward-modal .modal-body {
    padding: 1rem;
  }
  
  .list-group-item {
    padding: 10px 12px;
  }
  
  .nav-tabs .nav-link {
    padding: 6px 12px;
    font-size: 0.875rem;
  }
}
</style>

{{-- JavaScript for Forward Modal --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
  const forwardModal = document.getElementById('forward-modal');
  const forwardSourceId = document.getElementById('forward-source-id');
  const forwardSearch = document.getElementById('forward-search');
  const forwardConfirm = document.getElementById('forward-confirm');
  const forwardCount = document.getElementById('forward-count');
  const forwardSelectedCount = document.getElementById('forward-selected-count');
  
  let selectedTargets = new Set();
  let forwardData = { conversations: [], groups: [] };
  let currentModalInstance = null;

  // Initialize forward modal
  if (forwardModal) {
    initializeForwardModal();
  }

  function initializeForwardModal() {
    loadForwardData();
    setupEventListeners();
  }

  function loadForwardData() {
    // Try to get data from datasets element
    const datasets = document.getElementById('forward-datasets');
    if (datasets) {
      try {
        forwardData = JSON.parse(datasets.textContent || '{}');
      } catch (error) {
        console.error('Failed to parse forward data:', error);
        forwardData = { conversations: [], groups: [] };
      }
    } else {
      // Fallback: load data via API
      loadForwardDataFromAPI();
    }
  }

  async function loadForwardDataFromAPI() {
    try {
      const response = await fetch('/api/forward-targets');
      if (response.ok) {
        forwardData = await response.json();
      }
    } catch (error) {
      console.error('Failed to load forward data:', error);
    }
  }

  function setupEventListeners() {
    // Search functionality
    forwardSearch?.addEventListener('input', debounce(handleSearch, 300));

    // Tab change
    const tabs = forwardModal.querySelectorAll('[data-bs-toggle="tab"]');
    tabs.forEach(tab => {
      tab.addEventListener('shown.bs.tab', handleTabChange);
    });

    // Confirm forward
    forwardConfirm?.addEventListener('click', handleForwardConfirm);

    // Modal show/hide events
    forwardModal.addEventListener('show.bs.modal', handleModalShow);
    forwardModal.addEventListener('hidden.bs.modal', handleModalHide);
  }

  function handleModalShow(event) {
    selectedTargets.clear();
    updateSelectionCount();
    renderAllLists();
    
    // Store the modal instance
    currentModalInstance = bootstrap.Modal.getInstance(forwardModal);
  }

  function handleModalHide() {
    forwardSourceId.value = '';
    selectedTargets.clear();
    forwardSearch.value = '';
    currentModalInstance = null;
  }

  function handleSearch(event) {
    const query = event.target.value.toLowerCase().trim();
    renderAllLists(query);
  }

  function handleTabChange(event) {
    const activeTab = event.target.getAttribute('data-bs-target');
    renderTabContent(activeTab);
  }

  function renderAllLists(query = '') {
    renderSection('recent', filterData(forwardData.conversations, query));
    renderSection('contacts', filterData(forwardData.conversations, query));
    renderSection('groups', filterData(forwardData.groups, query));
    renderTabContent('#fwd-dms', query);
    renderTabContent('#fwd-groups', query);
  }

  function renderTabContent(tabId, query = '') {
    switch (tabId) {
      case '#fwd-dms':
        renderSection('dm', filterData(forwardData.conversations, query));
        break;
      case '#fwd-groups':
        renderSection('group', filterData(forwardData.groups, query));
        break;
      case '#fwd-all':
      default:
        renderAllLists(query);
        break;
    }
  }

  function filterData(items, query) {
    if (!query) return items;
    return items.filter(item => 
      (item.name || '').toLowerCase().includes(query) ||
      (item.phone || '').toLowerCase().includes(query) ||
      (item.title || '').toLowerCase().includes(query)
    );
  }

  function renderSection(type, items) {
    const container = document.getElementById(`forward-${type}-list`);
    if (!container) return;

    container.innerHTML = '';

    if (!items || !items.length) {
      container.innerHTML = `
        <div class="empty-state">
          <i class="bi bi-search" aria-hidden="true"></i>
          <p class="mb-0">No ${type} found</p>
        </div>
      `;
      return;
    }

    items.forEach(item => {
      const itemElement = createForwardItem(item, type);
      container.appendChild(itemElement);
    });
  }

  function createForwardItem(item, type) {
    const li = document.createElement('div');
    li.className = 'list-group-item list-group-item-action d-flex align-items-center';
    
    const targetKey = `${type}-${item.id}`;
    const isSelected = selectedTargets.has(targetKey);

    li.innerHTML = `
      <input type="checkbox" class="form-check-input me-3" 
             ${isSelected ? 'checked' : ''}
             id="forward-${targetKey}">
      ${createAvatar(item)}
      <div class="contact-info">
        <div class="contact-name">${escapeHtml(item.name || item.title || 'Unknown')}</div>
        <div class="contact-subtitle">${escapeHtml(item.subtitle || item.phone || '')}</div>
      </div>
    `;

    const checkbox = li.querySelector('input[type="checkbox"]');
    checkbox.addEventListener('change', () => {
      if (checkbox.checked) {
        selectedTargets.add(targetKey);
      } else {
        selectedTargets.delete(targetKey);
      }
      updateSelectionCount();
      
      // Update visual selection state
      if (checkbox.checked) {
        li.classList.add('selected');
      } else {
        li.classList.remove('selected');
      }
    });

    // Set initial selection state
    if (isSelected) {
      li.classList.add('selected');
    }

    // Click anywhere on item to toggle selection
    li.addEventListener('click', (e) => {
      if (e.target !== checkbox) {
        checkbox.checked = !checkbox.checked;
        checkbox.dispatchEvent(new Event('change'));
      }
    });

    return li;
  }

  function createAvatar(item) {
    if (item.avatar_url || item.avatar) {
      const avatarUrl = item.avatar_url || item.avatar;
      return `<img src="${avatarUrl}" class="avatar-img" alt="" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">`;
    }
    
    const initial = (item.name?.charAt(0) || item.title?.charAt(0) || '?').toUpperCase();
    
    return `<div class="avatar-placeholder">${initial}</div>`;
  }

  function updateSelectionCount() {
    const count = selectedTargets.size;
    if (forwardCount) forwardCount.textContent = count;
    if (forwardSelectedCount) forwardSelectedCount.textContent = count;
    if (forwardConfirm) forwardConfirm.disabled = count === 0;
  }

  async function handleForwardConfirm() {
    const messageId = forwardSourceId.value;
    
    if (!messageId) {
      showToast('No message selected to forward', 'error');
      return;
    }

    if (selectedTargets.size === 0) {
      showToast('Please select at least one recipient', 'error');
      return;
    }

    const targets = Array.from(selectedTargets).map(targetKey => {
      const [type, id] = targetKey.split('-');
      return { type: type === 'groups' || type === 'group' ? 'group' : 'conversation', id: parseInt(id) };
    });

    try {
      const context = forwardModal.dataset.context;
      const endpoint = context === 'group' 
        ? "{{ route('groups.forward.targets', $group ?? '') }}"
        : "{{ route('chat.forward.targets') }}";

      const response = await fetch(endpoint, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': "{{ csrf_token() }}"
        },
        body: JSON.stringify({ 
          message_id: parseInt(messageId), 
          targets 
        })
      });

      const result = await response.json();
      
      if (response.ok && result.success) {
        // FIX: Use the Bootstrap modal instance to close
        if (currentModalInstance) {
          currentModalInstance.hide();
        } else {
          // Fallback: trigger the close button
          const closeBtn = forwardModal.querySelector('[data-bs-dismiss="modal"]');
          if (closeBtn) {
            closeBtn.click();
          }
        }
        
        showToast(`Message forwarded to ${targets.length} conversation(s)`, 'success');
      } else {
        throw new Error(result.message || 'Forward failed');
      }

    } catch (error) {
      console.error('Forward error:', error);
      showToast(error.message || 'Failed to forward message', 'error');
    }
  }

  // Utility functions
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

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function showToast(message, type = 'info') {
    // Use your existing toast implementation
    if (typeof window.showToast === 'function') {
      window.showToast(message, type);
    } else {
      console.log(`[${type.toUpperCase()}] ${message}`);
    }
  }
});

// Public API for opening forward modal
window.openForwardModal = function(messageId, context = 'direct') {
  const forwardModal = document.getElementById('forward-modal');
  const forwardSourceId = document.getElementById('forward-source-id');
  
  if (forwardSourceId && messageId) {
    forwardSourceId.value = messageId;
  }
  
  // Update context if provided
  if (context && forwardModal) {
    forwardModal.dataset.context = context;
  }
  
  const modal = new bootstrap.Modal(forwardModal);
  modal.show();
};
</script>