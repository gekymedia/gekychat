@extends('layouts.app')

@section('title', 'World Feed - ' . config('app.name', 'GekyChat'))

{{-- Sidebar data is loaded by controller --}}

@section('content')
<div class="h-100 d-flex flex-column">
    <div class="chat-header border-bottom p-3">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h4 class="mb-0">World Feed</h4>
                <small class="text-muted">Discover content from around the world</small>
            </div>
            <button class="btn btn-wa btn-sm" id="create-post-btn">
                <i class="bi bi-plus-lg me-1"></i> Create Post
            </button>
        </div>
    </div>
    
    <div class="flex-grow-1 overflow-auto" id="world-feed-container" style="padding: 20px;">
        <div id="world-feed-loader" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted mt-2">Loading world feed...</p>
        </div>
        
        <div id="world-feed-posts" class="row g-4" style="display: none;">
            <!-- Posts will be loaded here -->
        </div>
        
        <div id="world-feed-empty" class="text-center py-5" style="display: none;">
            <i class="bi bi-globe display-1 text-muted mb-3"></i>
            <h5 class="mb-2">No posts yet</h5>
            <p class="text-muted">Be the first to share something with the world!</p>
            <button class="btn btn-wa mt-3" id="create-first-post-btn">
                <i class="bi bi-plus-lg me-1"></i> Create Your First Post
            </button>
        </div>
    </div>
</div>

<!-- Create Post Modal -->
<div class="modal fade" id="createPostModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create World Feed Post</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="create-post-form">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            <option value="text">Text</option>
                            <option value="image">Image</option>
                            <option value="video">Video</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Caption</label>
                        <textarea name="caption" class="form-control" rows="4" maxlength="500" placeholder="What's on your mind?"></textarea>
                        <small class="text-muted"><span id="caption-count">0</span>/500 characters</small>
                    </div>
                    <div class="mb-3" id="media-upload-section" style="display: none;">
                        <label class="form-label">Media <span class="text-danger">*</span></label>
                        <input type="file" name="media" class="form-control" accept="image/*,video/*">
                        <small class="text-muted">Supported: JPG, PNG, GIF, MP4, MOV, AVI (max 100MB)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-wa">Post</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentPage = 1;
    let isLoading = false;
    
    // Load posts
    async function loadPosts(page = 1) {
        if (isLoading) return;
        isLoading = true;
        
        try {
            const response = await fetch(`/api/v1/world-feed?page=${page}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || `Failed to load posts: ${response.status} ${response.statusText}`);
            }
            
            // Handle case where API returns an error message
            if (data.message && !data.data) {
                throw new Error(data.message);
            }
            
            // Handle both array and paginated response formats
            let posts = [];
            let pagination = {};
            
            if (Array.isArray(data.data)) {
                posts = data.data;
                pagination = data.pagination || { current_page: page, last_page: 1 };
            } else if (data.data && Array.isArray(data.data.data)) {
                // Laravel paginated response format
                posts = data.data.data;
                pagination = {
                    current_page: data.data.current_page || page,
                    last_page: data.data.last_page || 1,
                    per_page: data.data.per_page || 10,
                    total: data.data.total || posts.length
                };
            } else if (Array.isArray(data)) {
                posts = data;
                pagination = { current_page: 1, last_page: 1 };
            }
            
            document.getElementById('world-feed-loader').style.display = 'none';
            
            if (posts.length === 0 && page === 1) {
                document.getElementById('world-feed-empty').style.display = 'block';
                document.getElementById('world-feed-posts').style.display = 'none';
            } else {
                document.getElementById('world-feed-empty').style.display = 'none';
                document.getElementById('world-feed-posts').style.display = 'flex';
                renderPosts(posts, page === 1);
            }
            
            currentPage = pagination.current_page || 1;
        } catch (error) {
            console.error('Error loading posts:', error);
            const loaderDiv = document.getElementById('world-feed-loader');
            loaderDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Error:</strong> ${error.message || 'Failed to load posts. Please refresh the page.'}
                    <button class="btn btn-sm btn-outline-danger ms-2" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                </div>
            `;
        } finally {
            isLoading = false;
        }
    }
    
    function renderPosts(posts, clear = false) {
        const container = document.getElementById('world-feed-posts');
        if (clear) container.innerHTML = '';
        
        posts.forEach(post => {
            const col = document.createElement('div');
            col.className = 'col-md-6 col-lg-4';
            col.innerHTML = `
                <div class="card h-100">
                    ${post.media_url ? `
                        <img src="${post.thumbnail_url || post.media_url}" class="card-img-top" 
                             style="height: 200px; object-fit: cover;" alt="Post media">
                    ` : ''}
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <img src="${post.creator.avatar_url || '/images/default-avatar.png'}" 
                                 class="rounded-circle me-2" style="width: 32px; height: 32px;" 
                                 alt="${post.creator.name}">
                            <div>
                                <strong>${post.creator.name}</strong>
                                <small class="text-muted d-block">${new Date(post.created_at).toLocaleDateString()}</small>
                            </div>
                        </div>
                        ${post.caption ? `<p class="card-text">${post.caption}</p>` : ''}
                        <div class="d-flex justify-content-between text-muted small">
                            <span><i class="bi bi-heart"></i> ${post.likes_count || 0}</span>
                            <span><i class="bi bi-chat"></i> ${post.comments_count || 0}</span>
                            <span><i class="bi bi-eye"></i> ${post.views_count || 0}</span>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(col);
        });
    }
    
    // Create post handlers
    document.getElementById('create-post-btn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('createPostModal'));
        modal.show();
    });
    
    document.getElementById('create-first-post-btn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('createPostModal'));
        modal.show();
    });
    
    document.getElementById('create-post-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Posting...';
        
        try {
            const response = await fetch('/api/v1/world-feed/posts', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: formData
            });
            
            const data = await response.json();
            
            if (response.ok) {
                bootstrap.Modal.getInstance(document.getElementById('createPostModal')).hide();
                e.target.reset();
                // Reset media section visibility
                document.getElementById('media-upload-section').style.display = 'none';
                loadPosts(1);
            } else {
                const errorMsg = data.message || 'Failed to create post';
                alert(errorMsg);
            }
        } catch (error) {
            console.error('Error creating post:', error);
            alert('Failed to create post. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
    
    // Show/hide media upload based on type
    const typeSelect = document.querySelector('[name="type"]');
    if (typeSelect) {
        // Set initial state
        const mediaSection = document.getElementById('media-upload-section');
        if (mediaSection) {
            mediaSection.style.display = typeSelect.value !== 'text' ? 'block' : 'none';
        }
        
        typeSelect.addEventListener('change', (e) => {
            const mediaSection = document.getElementById('media-upload-section');
            const mediaInput = document.querySelector('[name="media"]');
            if (mediaSection) {
                mediaSection.style.display = e.target.value !== 'text' ? 'block' : 'none';
                // Clear file input when switching to text
                if (e.target.value === 'text' && mediaInput) {
                    mediaInput.value = '';
                }
                // Make media required when type is image or video
                if (mediaInput) {
                    mediaInput.required = e.target.value !== 'text';
                }
            }
        });
    }
    
    // Character counter
    document.querySelector('[name="caption"]')?.addEventListener('input', (e) => {
        document.getElementById('caption-count').textContent = e.target.value.length;
    });
    
    // Load initial posts
    loadPosts();
});
</script>
@endpush
@endsection
