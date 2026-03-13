{{-- Welcome/Onboarding Modal for First-Time Users (high z-index so it appears above contact sync and other modals) --}}
<div class="modal fade" id="onboardingModal" tabindex="-1" aria-labelledby="onboardingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered" style="z-index: 1061;">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-body p-0">
                {{-- Header with gradient --}}
                <div class="text-center text-white py-4 px-3" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);">
                    <div class="mb-3">
                        <img src="{{ asset('icons/icon-192x192.png') }}" alt="GekyChat" style="width: 64px; height: 64px; border-radius: 16px;">
                    </div>
                    <h4 class="mb-1 fw-bold">Welcome to GekyChat!</h4>
                    <p class="mb-0 opacity-90">Let's set up your profile</p>
                </div>
                
                {{-- Form --}}
                <form id="onboarding-form" enctype="multipart/form-data" class="p-4">
                    @csrf
                    
                    {{-- Avatar Upload --}}
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <div class="avatar-upload-container" style="width: 100px; height: 100px; cursor: pointer;" onclick="document.getElementById('onboarding-avatar').click()">
                                <img id="onboarding-avatar-preview" 
                                     src="{{ auth()->user()->avatar_url ?? asset('images/default-avatar.png') }}" 
                                     class="rounded-circle border shadow-sm"
                                     style="width: 100px; height: 100px; object-fit: cover;"
                                     alt="Profile photo"
                                     onerror="this.src='{{ asset('images/default-avatar.png') }}'">
                                <div class="avatar-upload-overlay rounded-circle d-flex align-items-center justify-content-center"
                                     style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); opacity: 0; transition: opacity 0.2s;">
                                    <i class="bi bi-camera-fill text-white" style="font-size: 24px;"></i>
                                </div>
                            </div>
                            <input type="file" id="onboarding-avatar" name="avatar" accept="image/*" class="d-none">
                            <div class="position-absolute" style="bottom: 0; right: 0;">
                                <span class="badge bg-success rounded-circle p-2" style="cursor: pointer;" onclick="document.getElementById('onboarding-avatar').click()">
                                    <i class="bi bi-plus"></i>
                                </span>
                            </div>
                        </div>
                        <p class="text-muted small mt-2 mb-0">Tap to add a photo</p>
                    </div>
                    
                    {{-- Name Input: empty value, current name as placeholder so user can type without clearing --}}
                    <div class="mb-4" data-initial-name="{{ e(auth()->user()->name) }}">
                        <label for="onboarding-name" class="form-label fw-semibold">Your Name</label>
                        <input type="text" 
                               class="form-control form-control-lg" 
                               id="onboarding-name" 
                               name="name" 
                               value=""
                               placeholder="{{ e(auth()->user()->name) }}"
                               maxlength="50"
                               required
                               style="border-radius: 12px;">
                        <small class="text-muted">This is how others will see you</small>
                    </div>
                    
                    {{-- Username: display with edit icon; click to make editable --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Your Username</label>
                        <div class="input-group position-relative">
                            <span class="input-group-text" style="border-radius: 12px 0 0 12px;">@</span>
                            <input type="text" 
                                   class="form-control form-control-lg" 
                                   id="onboarding-username"
                                   name="username"
                                   value="{{ auth()->user()->username ?? '' }}"
                                   readonly
                                   data-readonly="true"
                                   maxlength="20"
                                   style="border-radius: 0 12px 12px 0; background: var(--bg-accent, #f5f5f5); padding-right: 44px;">
                            <button type="button" 
                                    class="btn btn-link position-absolute p-2 text-secondary border-0 rounded-circle d-flex align-items-center justify-content-center onboarding-username-edit"
                                    style="right: 6px; top: 50%; transform: translateY(-50%); z-index: 2; background: transparent;"
                                    title="Edit username"
                                    aria-label="Edit username">
                                <i class="bi bi-pencil-fill" style="font-size: 1rem;"></i>
                            </button>
                        </div>
                        <small class="text-muted">You can change this later in Settings</small>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-outline-secondary flex-grow-1" id="onboarding-skip-btn" style="border-radius: 12px; padding: 12px;">
                    Skip for now
                </button>
                <button type="button" class="btn btn-wa flex-grow-1" id="onboarding-save-btn" style="border-radius: 12px; padding: 12px;">
                    <i class="bi bi-check-lg me-1"></i> Continue
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-upload-container:hover .avatar-upload-overlay {
    opacity: 1 !important;
}

#onboardingModal .modal-content {
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

[data-theme="dark"] #onboardingModal .form-control {
    background: var(--bg-accent, #202C33);
    border-color: var(--border, #2A3942);
    color: var(--text, #E5E7EB);
}

[data-theme="dark"] #onboardingModal .form-control:focus {
    background: var(--bg-accent, #202C33);
    border-color: #25D366;
}

[data-theme="dark"] #onboardingModal .input-group-text {
    background: var(--bg-accent, #202C33);
    border-color: var(--border, #2A3942);
    color: var(--text-muted, #8696A0);
}

#onboardingModal .onboarding-username-edit:hover {
    background: rgba(0,0,0,0.06) !important;
    color: #25D366 !important;
}

[data-theme="dark"] #onboardingModal .onboarding-username-edit:hover {
    background: rgba(255,255,255,0.1) !important;
    color: #25D366 !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('onboardingModal');
    if (!modal) return;
    
    const bsModal = new bootstrap.Modal(modal);
    const form = document.getElementById('onboarding-form');
    const avatarInput = document.getElementById('onboarding-avatar');
    const avatarPreview = document.getElementById('onboarding-avatar-preview');
    const nameInput = document.getElementById('onboarding-name');
    const usernameInput = document.getElementById('onboarding-username');
    const saveBtn = document.getElementById('onboarding-save-btn');
    const skipBtn = document.getElementById('onboarding-skip-btn');
    const nameWrapper = nameInput && nameInput.closest('[data-initial-name]');
    const initialName = nameWrapper ? nameWrapper.getAttribute('data-initial-name') : '';
    
    // Show modal
    bsModal.show();
    
    // Username: edit icon makes field editable
    document.querySelectorAll('.onboarding-username-edit').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!usernameInput) return;
            usernameInput.removeAttribute('readonly');
            usernameInput.setAttribute('data-readonly', 'false');
            usernameInput.style.background = '';
            usernameInput.focus();
        });
    });
    if (usernameInput) {
        usernameInput.addEventListener('focus', function() {
            if (this.getAttribute('data-readonly') === 'true') return;
            this.removeAttribute('readonly');
            this.setAttribute('data-readonly', 'false');
            this.style.background = '';
            this.classList.remove('is-invalid');
        });
        usernameInput.addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    }
    
    // Avatar preview
    avatarInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                avatarPreview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Save profile
    saveBtn.addEventListener('click', async function() {
        // If name is empty, use initial name (placeholder value) so they can skip typing
        const name = nameInput.value.trim() || (initialName || '').trim();
        
        if (!name) {
            nameInput.classList.add('is-invalid');
            nameInput.focus();
            return;
        }
        
        nameInput.classList.remove('is-invalid');
        
        const originalText = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
        
        try {
            const formData = new FormData();
            formData.append('name', name);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            
            if (usernameInput && usernameInput.value.trim()) {
                formData.append('username', usernameInput.value.trim());
            }
            
            // Add avatar if selected
            if (avatarInput.files[0]) {
                formData.append('avatar', avatarInput.files[0]);
            }
            
            const response = await fetch('/onboarding/complete', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            
            if (data.success) {
                bsModal.hide();
                showOnboardingToast('Profile updated! Welcome to GekyChat.', 'success');
                if (data.avatar_url) {
                    document.querySelectorAll('.user-avatar-current').forEach(img => {
                        img.src = data.avatar_url;
                    });
                }
            } else {
                const msg = (data.errors && data.errors.username && data.errors.username[0])
                    ? data.errors.username[0]
                    : (data.message || 'Failed to save profile');
                showOnboardingToast(msg, 'danger');
                if (data.errors && data.errors.username && usernameInput) {
                    usernameInput.classList.add('is-invalid');
                }
            }
        } catch (error) {
            console.error('Onboarding error:', error);
            showOnboardingToast('Failed to save profile. Please try again.', 'danger');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }
    });
    
    // Skip onboarding
    skipBtn.addEventListener('click', async function() {
        skipBtn.disabled = true;
        
        try {
            await fetch('/onboarding/skip', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                credentials: 'same-origin'
            });
            
            bsModal.hide();
        } catch (error) {
            console.error('Skip error:', error);
            bsModal.hide();
        }
    });
    
    function showOnboardingToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} position-fixed shadow-lg`;
        toast.style.cssText = 'bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 99999; min-width: 280px; max-width: 90%;';
        toast.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill'} me-2"></i>
                <span>${message}</span>
            </div>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
});
</script>
