@extends('layouts.app')

@section('title', 'Edit Profile')
@section('description', 'Update your GekyChat profile information, avatar, and settings.')

@section('content')
<style>
  .profile-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 18px;
    box-shadow: var(--wa-shadow);
    transition: var(--transition);
  }

  .profile-card:hover {
    box-shadow: 0 20px 40px rgba(0,0,0,.15);
  }

  .btn-wa {
    background: var(--wa-green);
    border: none;
    color: #062a1f;
    font-weight: 600;
    border-radius: 14px;
    transition: var(--transition);
    padding: 10px 24px;
  }

  .btn-wa:hover {
    filter: brightness(1.05);
    transform: translateY(-1px);
  }

  .btn-outline-secondary {
    border-radius: 14px;
    padding: 10px 24px;
    transition: var(--transition);
  }

  .avatar-preview {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border);
    transition: var(--transition);
    box-shadow: 0 4px 12px rgba(0,0,0,.1);
  }

  .avatar-preview:hover {
    border-color: var(--wa-green);
    transform: scale(1.05);
  }

  .avatar-upload-area {
    transition: var(--transition);
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    background: var(--input-bg);
  }

  .avatar-upload-area:hover {
    border-color: var(--wa-green);
    background: rgba(37, 211, 102, 0.05);
  }

  .avatar-upload-area.dragover {
    border-color: var(--wa-green);
    background: rgba(37, 211, 102, 0.1);
    transform: scale(1.02);
  }

  .form-control, .form-select {
    background: var(--input-bg);
    color: var(--text);
    border: 1px solid var(--input-border);
    border-radius: 12px;
    transition: var(--transition);
    padding: 12px 16px;
  }

  .form-control:focus, .form-select:focus {
    border-color: var(--wa-green);
    box-shadow: 0 0 0 0.2rem rgba(37, 211, 102, 0.25);
    background: var(--input-bg);
    color: var(--text);
  }

  .form-label {
    font-weight: 600;
    color: var(--text);
    margin-bottom: 8px;
  }

  .alert {
    border: none;
    border-radius: 12px;
    padding: 16px;
  }

  .alert-success {
    background: rgba(40, 167, 69, 0.1);
    color: var(--text);
    border-left: 4px solid #28a745;
  }

  .alert-danger {
    background: rgba(220, 53, 69, 0.1);
    color: var(--text);
    border-left: 4px solid #dc3545;
  }

  .character-count {
    font-size: 0.875rem;
    color: var(--wa-muted);
  }

  .character-count.warning {
    color: #ffc107;
  }

  .character-count.danger {
    color: #dc3545;
  }

  .profile-section {
    margin-bottom: 2rem;
  }

  .profile-section:last-child {
    margin-bottom: 0;
  }

  @media (max-width: 768px) {
    .avatar-preview {
      width: 80px;
      height: 80px;
    }
    
    .btn-wa, .btn-outline-secondary {
      width: 100%;
      margin-bottom: 12px;
    }
    
    .btn-outline-secondary {
      margin-left: 0 !important;
    }
  }
</style>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-8 col-xl-6">
      <div class="profile-card p-3 p-md-4 p-lg-5">
        <div class="d-flex align-items-center mb-4">
          <i class="bi bi-person-gear me-3" style="font-size: 1.5rem; color: var(--wa-green);"></i>
          <h1 class="h4 mb-0" style="font-weight: 700;">Profile Settings</h1>
        </div>

        @if (session('status')) 
          <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <div class="d-flex align-items-center">
              <i class="bi bi-check-circle-fill me-2" style="color: #28a745;"></i>
              <span>{{ session('status') }}</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        @endif
        
        @if ($errors->any())
          <div class="alert alert-danger mb-4" role="alert">
            <div class="d-flex align-items-center mb-2">
              <i class="bi bi-exclamation-triangle-fill me-2" style="color: #dc3545;"></i>
              <strong>Please fix the following issues:</strong>
            </div>
            <ul class="mb-0 ps-3">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" id="profileForm">
          @csrf

          <!-- Avatar Section -->
          <div class="profile-section">
            <h6 class="mb-3" style="font-weight: 600; color: var(--wa-green);">Profile Picture</h6>
            
            <div class="d-flex flex-column flex-md-row align-items-center gap-4">
              <div class="position-relative">
                <img id="avatarPreview" class="avatar-preview"
                     src="{{ $user->avatar_path ? asset('storage/' . $user->avatar_path) : asset('icons/icon-192x192.png') }}" 
                     alt="{{ $user->name ?? 'User' }}'s profile picture"
                     onerror="this.src='{{ asset('icons/icon-192x192.png') }}'">
                <div class="position-absolute bottom-0 end-0 bg-wa-green rounded-circle p-1 border border-2 border-card">
                  <i class="bi bi-camera-fill text-dark" style="font-size: 0.75rem;"></i>
                </div>
              </div>

              <div class="flex-grow-1 w-100">
                <label class="avatar-upload-area" for="avatarInput">
                  <i class="bi bi-cloud-arrow-up-fill d-block mb-2" style="font-size: 2rem; color: var(--wa-green);"></i>
                  <span class="d-block mb-1" style="font-weight: 600;">Click to upload photo</span>
                  <small class="text-muted d-block">JPG, PNG, or WebP • Max 2MB • Min 64×64px</small>
                </label>
                
                <input type="file" name="avatar" id="avatarInput" class="d-none" 
                       accept="image/jpeg,image/png,image/webp" 
                       onchange="previewAvatar(event)">
                
                @error('avatar')
                  <div class="text-danger small mt-2 d-flex align-items-center">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    {{ $message }}
                  </div>
                @enderror
              </div>
            </div>
          </div>

          <hr class="my-4" style="border-color: var(--border);">

          <!-- Personal Information Section -->
          <div class="profile-section">
            <h6 class="mb-3" style="font-weight: 600; color: var(--wa-green);">Personal Information</h6>
            
            <div class="mb-3">
              <label for="nameInput" class="form-label">Display Name</label>
              <input type="text" name="name" id="nameInput" class="form-control" 
                     maxlength="60" value="{{ old('name', $user->name) }}" 
                     placeholder="Enter your display name">
              <div class="d-flex justify-content-between align-items-center mt-1">
                <small class="text-muted">This name will be visible to other users</small>
                <small class="character-count" id="nameCount">0/60</small>
              </div>
              @error('name')
                <div class="text-danger small mt-2 d-flex align-items-center">
                  <i class="bi bi-exclamation-circle me-1"></i>
                  {{ $message }}
                </div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="aboutInput" class="form-label">About</label>
              <input type="text" name="about" id="aboutInput" class="form-control" 
                     maxlength="160" value="{{ old('about', $user->about) }}" 
                     placeholder="Hey there! I am using GekyChat.">
              <div class="d-flex justify-content-between align-items-center mt-1">
                <small class="text-muted">Tell others something about yourself</small>
                <small class="character-count" id="aboutCount">0/160</small>
              </div>
              @error('about')
                <div class="text-danger small mt-2 d-flex align-items-center">
                  <i class="bi bi-exclamation-circle me-1"></i>
                  {{ $message }}
                </div>
              @enderror
            </div>

            <div class="mb-0">
              <label class="form-label">Phone Number</label>
              <input class="form-control bg-light" type="text" value="{{ $user->phone }}" disabled
                     style="cursor: not-allowed; background: var(--input-bg) !important;">
              <small class="text-muted mt-1 d-block">
                <i class="bi bi-info-circle me-1"></i>
                Phone number is your primary login. Email & password can be added later for 2FA.
              </small>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 mt-4 pt-3 border-top" style="border-color: var(--border) !important;">
            <a href="{{ route('chat.index') }}" class="btn btn-outline-secondary order-2 order-sm-1">
              <i class="bi bi-arrow-left me-2"></i>Back to Chat
            </a>
            <button class="btn btn-wa order-1 order-sm-2" type="submit" id="submitBtn">
              <i class="bi bi-check-lg me-2"></i>Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Elements
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreview = document.getElementById('avatarPreview');
    const avatarUploadArea = document.querySelector('.avatar-upload-area');
    const nameInput = document.getElementById('nameInput');
    const aboutInput = document.getElementById('aboutInput');
    const nameCount = document.getElementById('nameCount');
    const aboutCount = document.getElementById('aboutCount');
    const profileForm = document.getElementById('profileForm');
    const submitBtn = document.getElementById('submitBtn');

    // Initialize character counts
    function updateCharacterCounts() {
      if (nameInput && nameCount) {
        const nameLength = nameInput.value.length;
        nameCount.textContent = `${nameLength}/60`;
        nameCount.className = `character-count ${nameLength > 50 ? 'warning' : ''} ${nameLength === 60 ? 'danger' : ''}`;
      }

      if (aboutInput && aboutCount) {
        const aboutLength = aboutInput.value.length;
        aboutCount.textContent = `${aboutLength}/160`;
        aboutCount.className = `character-count ${aboutLength > 140 ? 'warning' : ''} ${aboutLength === 160 ? 'danger' : ''}`;
      }
    }

    // Avatar preview function
    function previewAvatar(event) {
      const file = event.target.files[0];
      if (file) {
        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
          alert('File size must be less than 2MB');
          event.target.value = '';
          return;
        }

        // Validate image dimensions
        const img = new Image();
        img.onload = function() {
          if (this.width < 64 || this.height < 64) {
            alert('Image must be at least 64x64 pixels');
            event.target.value = '';
            return;
          }
          
          // Show preview
          avatarPreview.src = URL.createObjectURL(file);
          avatarPreview.onload = function() {
            URL.revokeObjectURL(this.src);
          };
        };
        img.src = URL.createObjectURL(file);
      }
    }

    // Drag and drop functionality
    if (avatarUploadArea) {
      // Prevent default drag behaviors
      ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        avatarUploadArea.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
      });

      // Highlight drop area when item is dragged over it
      ['dragenter', 'dragover'].forEach(eventName => {
        avatarUploadArea.addEventListener(eventName, highlight, false);
      });

      ['dragleave', 'drop'].forEach(eventName => {
        avatarUploadArea.addEventListener(eventName, unhighlight, false);
      });

      // Handle dropped files
      avatarUploadArea.addEventListener('drop', handleDrop, false);

      function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
      }

      function highlight() {
        avatarUploadArea.classList.add('dragover');
      }

      function unhighlight() {
        avatarUploadArea.classList.remove('dragover');
      }

      function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        avatarInput.files = files;
        
        // Trigger change event for preview
        const event = new Event('change', { bubbles: true });
        avatarInput.dispatchEvent(event);
      }
    }

    // Click on upload area triggers file input
    if (avatarUploadArea && avatarInput) {
      avatarUploadArea.addEventListener('click', () => {
        avatarInput.click();
      });
    }

    // Character count updates
    if (nameInput) {
      nameInput.addEventListener('input', updateCharacterCounts);
    }

    if (aboutInput) {
      aboutInput.addEventListener('input', updateCharacterCounts);
    }

    // Form submission handling
    if (profileForm && submitBtn) {
      profileForm.addEventListener('submit', function(e) {
        // Add loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-arrow-repeat spinner me-2"></i>Saving...';
        
        // You could add additional validation here if needed
      });
    }

    // Initialize counts on page load
    updateCharacterCounts();

    // Add input event listeners for real-time validation
    if (nameInput) {
      nameInput.addEventListener('input', function() {
        this.value = this.value.replace(/^\s+/, ''); // Remove leading spaces
      });
    }

    if (aboutInput) {
      aboutInput.addEventListener('input', function() {
        this.value = this.value.replace(/^\s+/, ''); // Remove leading spaces
      });
    }
  });

  // Global function for avatar preview (for inline onchange)
  function previewAvatar(event) {
    const file = event.target.files[0];
    if (file) {
      const preview = document.getElementById('avatarPreview');
      preview.src = URL.createObjectURL(file);
      
      // Revoke object URL after load to avoid memory leaks
      preview.onload = function() {
        URL.revokeObjectURL(preview.src);
      };
    }
  }
</script>

<style>
  .spinner {
    animation: spin 1s linear infinite;
  }

  @keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }

  .bg-light {
    background-color: var(--input-bg) !important;
  }
</style>
@endsection