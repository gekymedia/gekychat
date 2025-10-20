{{-- resources/views/groups/partials/group_management_modal.blade.php --}}
@php
  use Illuminate\Support\Facades\Storage;
  use Illuminate\Support\Str;

  $groupData = $groupData ?? [
    'name' => $group->name ?? 'Group Chat',
    'description' => $group->description ?? null,
    'avatar' => $group->avatar_path ? Storage::url($group->avatar_path) : null,
    'isPrivate' => $group->is_private ?? false,
    'memberCount' => $group->members->count() ?? 0,
    'isOwner' => $group->owner_id === auth()->id(),
    'userRole' => $group->members->firstWhere('id', auth()->id())?->pivot?->role ?? 'member'
  ];
@endphp

{{-- Group Settings Modal --}}
<div class="modal fade" id="editGroupModal" tabindex="-1" aria-labelledby="editGroupLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="edit-group-form"
          action="{{ route('groups.update', $group) }}"
          method="POST" enctype="multipart/form-data" novalidate>
      @csrf
      @method('PUT')

      <div class="modal-header">
        <h2 class="modal-title h5" id="editGroupLabel">Edit Group</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        {{-- Avatar Upload --}}
        <div class="d-flex align-items-center gap-3 mb-4">
          <div class="position-relative">
            <img id="groupAvatarPreview" src="{{ $groupData['avatar'] ?: asset('images/group-default.png') }}"
                 class="rounded-circle group-settings-avatar" 
                 alt="Group avatar preview"
                 onerror="this.src='{{ asset('images/group-default.png') }}'">
            <div class="position-absolute bottom-0 end-0 bg-primary rounded-circle p-1 border border-2 border-white">
              <i class="bi bi-camera text-white" style="font-size: 0.75rem;"></i>
            </div>
          </div>
          <div class="flex-grow-1">
            <label for="groupAvatarInput" class="form-label mb-1 fw-medium">Group Avatar</label>
            <input type="file" name="avatar" id="groupAvatarInput" class="form-control" 
                   accept="image/png,image/jpeg,image/webp" aria-describedby="avatarHelp">
            <div id="avatarHelp" class="form-text">PNG, JPG or WebP. Max 2MB.</div>
          </div>
        </div>

        {{-- Group Name --}}
        <div class="mb-3">
          <label for="groupNameInput" class="form-label fw-medium">Group Name</label>
          <input type="text" name="name" id="groupNameInput" class="form-control" 
                 value="{{ $group->name }}" maxlength="64" required
                 placeholder="Enter group name"
                 aria-describedby="nameHelp">
          <div id="nameHelp" class="form-text">Maximum 64 characters</div>
        </div>

        {{-- Group Description --}}
        <div class="mb-3">
          <label for="groupDescriptionInput" class="form-label fw-medium">Description</label>
          <textarea name="description" id="groupDescriptionInput" class="form-control" 
                    rows="3" maxlength="500" placeholder="Optional group description"
                    aria-describedby="descriptionHelp">{{ $group->description }}</textarea>
          <div class="form-text d-flex justify-content-between">
            <span id="description-counter">0</span>/500 characters
          </div>
        </div>

        {{-- Privacy Setting --}}
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" role="switch" id="is_private_switch"
                 name="is_private" value="1" {{ $groupData['isPrivate'] ? 'checked' : '' }}
                 aria-describedby="privacyHelp">
          <label class="form-check-label fw-medium" for="is_private_switch">
            <i class="bi bi-lock-fill me-2" aria-hidden="true"></i>
            Private group
          </label>
          <div id="privacyHelp" class="form-text">
            Private groups require invitation to join
          </div>
        </div>

        {{-- Member Management Section (Owner/Admin only) --}}
        @if($groupData['isOwner'] || $groupData['userRole'] === 'admin')
          <div class="group-management-section border-top pt-3 mt-3">
            <h3 class="h6 group-management-title">
              <i class="bi bi-people-fill" aria-hidden="true"></i>
              Member Management
            </h3>
            
            <div class="members-list" style="max-height: 200px; overflow-y: auto;">
              @foreach($group->members as $member)
                @php
                  $isSelf = $member->id === auth()->id();
                  $isOwner = $group->owner_id === $member->id;
                  $isAdmin = optional($member->pivot)->role === 'admin';
                  $canManage = !$isSelf && ($groupData['isOwner'] || ($groupData['userRole'] === 'admin' && !$isOwner && !$isAdmin));
                @endphp
                
                <div class="member-item d-flex align-items-center justify-content-between py-2">
                  <div class="d-flex align-items-center gap-3 flex-grow-1">
                    {{-- Member Avatar --}}
                    @if($member->avatar_path)
                      <img src="{{ Storage::url($member->avatar_path) }}" class="member-avatar" 
                           alt="{{ $member->name ?? $member->phone }} avatar">
                    @else
                      <div class="member-avatar bg-avatar text-white d-flex align-items-center justify-content-center">
                        <small>{{ Str::upper(Str::substr($member->name ?? $member->phone ?? 'U', 0, 1)) }}</small>
                      </div>
                    @endif

                    {{-- Member Info --}}
                    <div class="flex-grow-1 min-width-0">
                      <div class="d-flex align-items-center gap-2">
                        <strong class="text-truncate d-block">
                          {{ $member->name ?? $member->phone ?? 'Unknown User' }}
                        </strong>
                        @if($isOwner)
                          <span class="member-role-badge role-owner" title="Group owner">Owner</span>
                        @elseif($isAdmin)
                          <span class="member-role-badge role-admin" title="Group admin">Admin</span>
                        @endif
                      </div>
                      @if($isSelf)
                        <small class="text-muted">You</small>
                      @endif
                    </div>
                  </div>

                  {{-- Management Actions --}}
                  @if($canManage)
                    <div class="member-management-actions d-flex gap-1">
                      @if(!$isAdmin && ($groupData['isOwner'] || $groupData['userRole'] === 'admin'))
                        <button class="btn btn-sm action-btn promote" 
                                type="button"
                                data-member-id="{{ $member->id }}"
                                data-member-name="{{ $member->name ?? $member->phone }}"
                                title="Promote to admin"
                                aria-label="Promote {{ $member->name ?? $member->phone }} to admin">
                          <i class="bi bi-arrow-up" aria-hidden="true"></i>
                        </button>
                      @endif
                      
                      @if($groupData['isOwner'] && !$isOwner)
                        <button class="btn btn-sm action-btn remove" 
                                type="button"
                                data-member-id="{{ $member->id }}"
                                data-member-name="{{ $member->name ?? $member->phone }}"
                                title="Remove from group"
                                aria-label="Remove {{ $member->name ?? $member->phone }} from group">
                          <i class="bi bi-person-dash" aria-hidden="true"></i>
                        </button>
                      @endif
                    </div>
                  @endif
                </div>
              @endforeach
            </div>
          </div>
        @endif
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="edit-group-save">
          <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" style="display: none;"></span>
          Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

{{-- Invite Link Modal --}}
<div class="modal fade" id="inviteModal" tabindex="-1" aria-labelledby="inviteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="inviteModalLabel">Group Invite</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="invite-section border-0 p-0">
          <p class="text-muted mb-3">Share this link to invite others to the group:</p>
          
          <div class="invite-link-container mb-3">
            <input type="text" class="invite-link" id="invite-link-input" 
                   value="{{ route('groups.show', $group) }}" readonly
                   aria-label="Group invite link">
            <button class="btn btn-primary copy-invite-btn" type="button" id="copy-invite-modal">
              <i class="bi bi-copy" aria-hidden="true"></i>
            </button>
          </div>
          
          <div class="invite-stats">
            <div class="stat-item">
              <i class="bi bi-people" aria-hidden="true"></i>
              <span>{{ $groupData['memberCount'] }} members</span>
            </div>
            @if($groupData['isPrivate'])
              <div class="stat-item">
                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                <span>Private</span>
              </div>
            @endif
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

{{-- Leave Group Confirmation Modal --}}
<div class="modal fade" id="leaveGroupModal" tabindex="-1" aria-labelledby="leaveGroupModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="leaveGroupModalLabel">Leave Group</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="text-center py-3">
          <i class="bi bi-exclamation-triangle display-4 text-warning mb-3" aria-hidden="true"></i>
          <h3 class="h6 mb-2">Are you sure you want to leave "{{ $groupData['name'] }}"?</h3>
          <p class="text-muted mb-0">
            You will no longer receive messages from this group and will need to be re-invited to join again.
          </p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <form method="POST" action="{{ route('groups.leave', $group) }}" class="d-inline">
          @csrf
          <button type="submit" class="btn btn-danger">Leave Group</button>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- JavaScript for Group Management --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
  const editGroupModal = document.getElementById('editGroupModal');
  const editGroupForm = document.getElementById('edit-group-form');
  const groupAvatarInput = document.getElementById('groupAvatarInput');
  const groupAvatarPreview = document.getElementById('groupAvatarPreview');
  const groupDescriptionInput = document.getElementById('groupDescriptionInput');
  const descriptionCounter = document.getElementById('description-counter');
  const copyInviteBtn = document.getElementById('copy-invite-modal');
  const inviteLinkInput = document.getElementById('invite-link-input');

  // Initialize group management functionality
  if (editGroupModal) {
    initializeGroupManagement();
  }

  function initializeGroupManagement() {
    setupAvatarUpload();
    setupFormValidation();
    setupMemberActions();
    setupInviteSystem();
  }

  function setupAvatarUpload() {
    if (groupAvatarInput && groupAvatarPreview) {
      groupAvatarInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
          if (!validateImageFile(file)) {
            this.value = '';
            showToast('Please select a valid image file (PNG, JPG, WebP) under 2MB', 'error');
            return;
          }

          const reader = new FileReader();
          reader.onload = function(e) {
            groupAvatarPreview.src = e.target.result;
          };
          reader.readAsDataURL(file);
        }
      });

      // Drag and drop for avatar
      const avatarArea = groupAvatarPreview.parentElement;
      if (avatarArea) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
          avatarArea.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
          avatarArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
          avatarArea.addEventListener(eventName, unhighlight, false);
        });

        avatarArea.addEventListener('drop', handleDrop, false);
      }

      function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
      }

      function highlight() {
        avatarArea.classList.add('dragover');
      }

      function unhighlight() {
        avatarArea.classList.remove('dragover');
      }

      function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        groupAvatarInput.files = files;
        groupAvatarInput.dispatchEvent(new Event('change'));
      }
    }
  }

  function validateImageFile(file) {
    const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
    const maxSize = 2 * 1024 * 1024; // 2MB

    if (!validTypes.includes(file.type)) {
      return false;
    }

    if (file.size > maxSize) {
      return false;
    }

    return true;
  }

  function setupFormValidation() {
    // Character counter for description
    if (groupDescriptionInput && descriptionCounter) {
      updateCounter(groupDescriptionInput.value.length);
      groupDescriptionInput.addEventListener('input', function() {
        updateCounter(this.value.length);
      });

      function updateCounter(length) {
        descriptionCounter.textContent = length;
        if (length > 450) {
          descriptionCounter.classList.add('text-warning');
        } else {
          descriptionCounter.classList.remove('text-warning');
        }
      }
    }

    // Form submission
    if (editGroupForm) {
      editGroupForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('#edit-group-save');
        const spinner = submitBtn.querySelector('.spinner-border');
        
        submitBtn.disabled = true;
        spinner.style.display = 'inline-block';

        try {
          const formData = new FormData(this);
          const response = await fetch(this.action, {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
          }

          const result = await response.json();
          
          if (result.success) {
            showToast('Group updated successfully', 'success');
            bootstrap.Modal.getInstance(editGroupModal).hide();
            
            // Update UI with new data
            setTimeout(() => window.location.reload(), 1000);
          } else {
            throw new Error(result.message || 'Failed to update group');
          }
          
        } catch (error) {
          handleGroupUpdateError(error);
        } finally {
          submitBtn.disabled = false;
          spinner.style.display = 'none';
        }
      });
    }
  }

  function handleGroupUpdateError(error) {
    console.error('Group update error:', error);
    
    if (error.status === 422) {
      showToast('Please check your input and try again', 'error');
    } else if (error.status === 403) {
      showToast('You do not have permission to edit this group', 'error');
    } else {
      showToast('Failed to update group. Please try again.', 'error');
    }
  }

  function setupMemberActions() {
    // Delegated event handling for member actions
    editGroupModal.addEventListener('click', async function(e) {
      const promoteBtn = e.target.closest('.promote');
      const removeBtn = e.target.closest('.remove');
      
      if (promoteBtn) {
        e.preventDefault();
        await handleMemberPromotion(promoteBtn);
      }
      
      if (removeBtn) {
        e.preventDefault();
        await handleMemberRemoval(removeBtn);
      }
    });
  }

  async function handleMemberPromotion(button) {
    const memberId = button.dataset.memberId;
    const memberName = button.dataset.memberName;
    
    if (!confirm(`Promote ${memberName} to admin?`)) return;
    
    try {
      const response = await fetch(`/groups/{{ $group->id }}/members/${memberId}/promote`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      
      showToast(`${memberName} promoted to admin`, 'success');
      setTimeout(() => window.location.reload(), 1000);
    } catch (error) {
      handleMemberActionError(error, 'promote');
    }
  }

  async function handleMemberRemoval(button) {
    const memberId = button.dataset.memberId;
    const memberName = button.dataset.memberName;
    
    if (!confirm(`Remove ${memberName} from the group?`)) return;
    
    try {
      const response = await fetch(`/groups/{{ $group->id }}/members/${memberId}`, {
        method: 'DELETE',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      
      showToast(`${memberName} removed from group`, 'success');
      setTimeout(() => window.location.reload(), 1000);
    } catch (error) {
      handleMemberActionError(error, 'remove');
    }
  }

  function handleMemberActionError(error, action) {
    console.error(`Member ${action} error:`, error);
    
    if (error.status === 403) {
      showToast(`You don't have permission to ${action} members`, 'error');
    } else if (error.status === 404) {
      showToast('Member not found', 'error');
    } else {
      showToast(`Failed to ${action} member. Please try again.`, 'error');
    }
  }

  function setupInviteSystem() {
    if (copyInviteBtn && inviteLinkInput) {
      copyInviteBtn.addEventListener('click', async function() {
        try {
          await navigator.clipboard.writeText(inviteLinkInput.value);
          
          // Visual feedback
          const originalHtml = this.innerHTML;
          this.innerHTML = '<i class="bi bi-check2"></i>';
          this.classList.add('copied');
          
          setTimeout(() => {
            this.innerHTML = originalHtml;
            this.classList.remove('copied');
          }, 2000);
          
          showToast('Invite link copied to clipboard', 'success');
        } catch (err) {
          // Fallback for browsers that don't support clipboard API
          inviteLinkInput.select();
          document.execCommand('copy');
          showToast('Invite link copied to clipboard', 'success');
        }
      });
    }
  }

  // Public API for group management
  window.groupManagement = {
    showEditModal: () => {
      const modal = new bootstrap.Modal(editGroupModal);
      modal.show();
    },
    showInviteModal: () => {
      const modal = new bootstrap.Modal(document.getElementById('inviteModal'));
      modal.show();
    },
    showLeaveModal: () => {
      const modal = new bootstrap.Modal(document.getElementById('leaveGroupModal'));
      modal.show();
    }
  };
});

// Global helper function
function showToast(message, type = 'info') {
  // Use your existing toast implementation
  console.log(`[${type.toUpperCase()}] ${message}`);
}
</script>