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
            <div class="d-flex gap-2">
                <button class="btn btn-danger btn-sm" id="go-live-btn">
                    <i class="bi bi-camera-video me-1"></i> Go Live
                </button>
                <button class="btn btn-wa btn-sm" id="create-post-btn">
                    <i class="bi bi-plus-lg me-1"></i> Create Post
                </button>
            </div>
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

<!-- Go Live Modal -->
<div class="modal fade" id="goLiveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Start Live Broadcast</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="go-live-form">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required maxlength="100" placeholder="What are you broadcasting about?">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" maxlength="500" placeholder="Optional description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-wa">Start Broadcast</button>
                </div>
            </form>
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
                        <label class="form-label">Media <span class="text-danger">*</span></label>
                        <input type="file" name="media" class="form-control" accept="image/*,video/*" required>
                        <small class="text-muted">Supported: JPG, PNG, GIF, MP4, MOV, AVI (max 100MB). Media is required - World feed only supports image or video posts.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Caption <span class="text-muted">(Optional)</span></label>
                        <textarea name="caption" class="form-control" rows="4" maxlength="500" placeholder="Add a caption..."></textarea>
                        <small class="text-muted"><span id="caption-count">0</span>/500 characters</small>
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
            const response = await fetch(`/world-feed/posts?page=${page}`, {
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
            const isVideo = (post.type === 'video' || post.media_type === 'video') || (post.media_url && post.media_url.match(/\.(mp4|webm|ogg|mov|avi)$/i));
            const mediaUrl = post.media_url || null;
            const thumbnailUrl = post.thumbnail_url || null;
            col.innerHTML = `
                <div class="card h-100">
                    ${mediaUrl ? (isVideo ? `
                        <video class="card-img-top" style="height: 200px; object-fit: cover; width: 100%;" controls preload="metadata">
                            <source src="${escapeHtml(mediaUrl)}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    ` : `
                        <img src="${escapeHtml(thumbnailUrl || mediaUrl)}" class="card-img-top" 
                             style="height: 200px; object-fit: cover; cursor: pointer;" alt="Post media"
                             onclick="window.open('${escapeHtml(mediaUrl)}', '_blank')">
                    `) : ''}
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
    
    // Go Live handler - show modal like on live-broadcast page
    document.getElementById('go-live-btn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('goLiveModal'));
        modal.show();
    });
    
    // Handle go live form submission
    document.getElementById('go-live-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Starting...';
        
        try {
            const response = await fetch('/live-broadcast/start', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: formData
            });
            
            const data = await response.json();
            
            if (response.ok) {
                bootstrap.Modal.getInstance(document.getElementById('goLiveModal')).hide();
                e.target.reset();
                // Redirect to broadcast page or show success
                if (data.broadcast_id || data.data?.id) {
                    const broadcastId = data.broadcast_id || data.data.id;
                    window.location.href = `/live-broadcast/${broadcastId}`;
                } else {
                    alert('Broadcast started successfully');
                }
            } else {
                if (response.status === 401) {
                    alert('Unauthenticated. Please refresh the page and try again.');
                } else {
                    alert(data.message || 'Failed to start broadcast');
                }
            }
        } catch (error) {
            console.error('Error starting broadcast:', error);
            alert('Failed to start broadcast. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
    
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
            const response = await fetch('/world-feed/posts', {
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
    
    // Character counter
    document.querySelector('[name="caption"]')?.addEventListener('input', (e) => {
        document.getElementById('caption-count').textContent = e.target.value.length;
    });
    
    // Load initial posts
    loadPosts();
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
@endpush
@endsection
