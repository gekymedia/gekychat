@extends('layouts.app')

@section('title', 'World Feed - ' . config('app.name', 'GekyChat'))

{{-- Sidebar data is loaded by controller --}}

@push('styles')
<style>
    #world-feed-container {
        background-color: #fafafa;
        min-height: calc(100vh - 60px);
    }
    
    #world-feed-posts {
        display: block !important;
        width: 100%;
    }
    
    .world-feed-post {
        background: white;
        border: 1px solid #dbdbdb;
        border-radius: 8px;
        margin-bottom: 24px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        width: 100%;
        display: block;
    }
    
    .world-feed-post .like-btn:hover,
    .world-feed-post .comment-btn:hover,
    .world-feed-post .share-btn:hover,
    .world-feed-post .save-btn:hover {
        opacity: 0.7;
    }
    
    .world-feed-post img,
    .world-feed-post video {
        width: 100%;
        display: block;
    }
    
    /* Fix modal z-index issues */
    .modal-backdrop {
        z-index: 1040 !important;
    }
    
    .modal {
        z-index: 1055 !important;
    }
    
    .chat-header {
        z-index: 1030 !important;
    }
    
    @media (max-width: 768px) {
        #world-feed-posts {
            max-width: 100% !important;
            padding: 0 !important;
        }
        .world-feed-post {
            border-radius: 0;
            border-left: none;
            border-right: none;
            margin-bottom: 0;
        }
    }
</style>
@endpush

@section('content')
<div class="h-100 d-flex flex-column">
    <div class="chat-header border-bottom p-3" style="background: white; position: sticky; top: 0; z-index: 100;">
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
    
    <div class="flex-grow-1 overflow-auto" id="world-feed-container">
        <div id="world-feed-loader" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted mt-2">Loading world feed...</p>
        </div>
        
        <!-- Instagram-style vertical scrolling feed -->
        <div id="world-feed-posts" style="display: none; max-width: 614px; margin: 0 auto; padding: 20px 0;">
            <!-- Posts will be loaded here -->
        </div>
        
        <div id="world-feed-empty" class="text-center py-5" style="display: none; max-width: 614px; margin: 0 auto; padding: 40px 20px;">
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
                        <input type="file" name="media" id="post-media-input" class="form-control" accept="image/*,video/*" required>
                        <small class="text-muted">Supported: JPG, PNG, GIF, MP4, MOV, AVI (max 100MB). Media is required - World feed only supports image or video posts.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Caption <span class="text-muted">(Optional)</span></label>
                        <textarea name="caption" class="form-control" rows="4" maxlength="500" placeholder="Add a caption..."></textarea>
                        <small class="text-muted"><span id="caption-count">0</span>/500 characters</small>
                    </div>
                    <div class="mb-3" id="audio-section" style="display: none;">
                        <label class="form-label">Background Audio <span class="text-muted">(Optional)</span></label>
                        <div class="card">
                            <div class="card-body">
                                <div id="audio-selection-empty">
                                    <button type="button" class="btn btn-outline-wa btn-sm" id="add-audio-btn">
                                        <i class="bi bi-music-note me-1"></i> Add Audio
                                    </button>
                                    <small class="text-muted d-block mt-2">Add background music to your video</small>
                                </div>
                                <div id="audio-selection-filled" style="display: none;">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <strong id="selected-audio-name"></strong>
                                            <br>
                                            <small class="text-muted" id="selected-audio-artist"></small>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-danger" id="remove-audio-btn">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                    <div class="mt-3">
                                        <label class="form-label small">Volume</label>
                                        <input type="range" class="form-range" id="audio-volume-slider" min="0" max="100" value="100">
                                        <small class="text-muted" id="audio-volume-label">100%</small>
                                    </div>
                                    <div class="alert alert-warning mt-2 py-2" id="audio-attribution-alert" style="display: none;">
                                        <small><i class="bi bi-info-circle me-1"></i><span id="audio-attribution-text"></span></small>
                                    </div>
                                    <input type="hidden" name="audio_id" id="audio-id-input">
                                    <input type="hidden" name="audio_volume" id="audio-volume-input" value="100">
                                </div>
                            </div>
                        </div>
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
                document.getElementById('world-feed-posts').style.display = 'block';
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
            const postDiv = document.createElement('div');
            postDiv.className = 'world-feed-post';
            postDiv.style.cssText = 'background: white; border: 1px solid #dbdbdb; border-radius: 8px; margin-bottom: 24px; overflow: hidden;';
            
            const isVideo = (post.type === 'video' || post.media_type === 'video') || (post.media_url && post.media_url.match(/\.(mp4|webm|ogg|mov|avi)$/i));
            
            // Ensure URLs are properly formatted (handle relative URLs)
            let mediaUrl = post.media_url || null;
            let thumbnailUrl = post.thumbnail_url || null;
            
            // If URL is relative, make it absolute
            if (mediaUrl && mediaUrl.startsWith('/') && !mediaUrl.startsWith('//')) {
                mediaUrl = window.location.origin + mediaUrl;
            }
            if (thumbnailUrl && thumbnailUrl.startsWith('/') && !thumbnailUrl.startsWith('//')) {
                thumbnailUrl = window.location.origin + thumbnailUrl;
            }
            
            // Check if post has audio
            const hasAudio = post.has_audio && post.audio;
            const audioAttribution = hasAudio && post.audio.attribution ? `
                <div class="p-2 bg-light border-top">
                    <small class="text-muted"><i class="bi bi-music-note me-1"></i>${escapeHtml(post.audio.attribution)}</small>
                </div>
            ` : '';
            
            // Format date
            const postDate = new Date(post.created_at);
            const timeAgo = getTimeAgo(postDate);
            
            // Avatar URL
            let avatarUrl = post.creator?.avatar_url || '/images/default-avatar.png';
            if (avatarUrl && avatarUrl.startsWith('/') && !avatarUrl.startsWith('//')) {
                avatarUrl = window.location.origin + avatarUrl;
            }
            
            postDiv.innerHTML = `
                <!-- Post Header -->
                <div class="d-flex align-items-center p-3 border-bottom" style="background: white;">
                    <img src="${escapeHtml(avatarUrl)}" 
                         class="rounded-circle me-3" 
                         style="width: 32px; height: 32px; object-fit: cover; cursor: pointer;" 
                         alt="${escapeHtml(post.creator.name)}"
                         onerror="this.src='${window.location.origin}/images/default-avatar.png'"
                         onclick="window.location.href='/profile?user=${post.creator.id || ''}'">
                    <div class="flex-grow-1">
                        <strong style="cursor: pointer;" onclick="window.location.href='/profile?user=${post.creator.id || ''}'">${escapeHtml(post.creator.name)}</strong>
                        ${post.creator.username ? `<small class="text-muted d-block">@${escapeHtml(post.creator.username)}</small>` : ''}
                    </div>
                    ${hasAudio ? `
                        <span class="badge bg-wa me-2">
                            <i class="bi bi-music-note"></i> Audio
                        </span>
                    ` : ''}
                    <button class="btn btn-sm btn-link text-dark" style="font-size: 20px;">⋯</button>
                </div>
                
                <!-- Post Media -->
                ${mediaUrl ? (isVideo ? `
                    <div class="position-relative" style="background: black; aspect-ratio: 1;">
                        <video 
                            class="w-100" 
                            style="max-height: 614px; object-fit: contain; display: block;" 
                            controls 
                            preload="metadata"
                            onclick="this.paused ? this.play() : this.pause()">
                            <source src="${escapeHtml(mediaUrl)}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                ` : `
                    <div style="background: black; aspect-ratio: 1; display: flex; align-items: center; justify-content: center;">
                        <img src="${escapeHtml(thumbnailUrl || mediaUrl)}" 
                             class="w-100" 
                             style="max-height: 614px; object-fit: contain; cursor: pointer; display: block;" 
                             alt="Post media"
                             onclick="window.open('${escapeHtml(mediaUrl)}', '_blank')"
                             onerror="this.style.display='none'">
                    </div>
                `) : ''}
                
                <!-- Post Actions -->
                <div class="px-3 py-2">
                    <div class="d-flex align-items-center mb-2">
                        <button class="btn btn-sm p-0 me-3 like-btn" data-post-id="${post.id}" style="border: none; background: none; font-size: 24px;">
                            <i class="bi ${post.is_liked ? 'bi-heart-fill text-danger' : 'bi-heart'}"></i>
                        </button>
                        <button class="btn btn-sm p-0 me-3 comment-btn" data-post-id="${post.id}" style="border: none; background: none; font-size: 24px;">
                            <i class="bi bi-chat"></i>
                        </button>
                        <button class="btn btn-sm p-0 me-3 share-btn" data-post-id="${post.id}" style="border: none; background: none; font-size: 24px;">
                            <i class="bi bi-send"></i>
                        </button>
                        <div class="flex-grow-1"></div>
                        <button class="btn btn-sm p-0 save-btn" data-post-id="${post.id}" style="border: none; background: none; font-size: 24px;">
                            <i class="bi bi-bookmark"></i>
                        </button>
                    </div>
                    <div class="mb-2">
                        <strong>${post.likes_count || 0} likes</strong>
                    </div>
                    ${post.caption ? `
                        <div class="mb-1">
                            <strong>${escapeHtml(post.creator.name)}</strong> 
                            <span>${escapeHtml(post.caption)}</span>
                        </div>
                    ` : ''}
                    ${post.comments_count > 0 ? `
                        <button class="btn btn-link p-0 text-muted text-start mb-2 view-comments-btn" data-post-id="${post.id}" style="text-decoration: none; font-size: 14px;">
                            View all ${post.comments_count} comments
                        </button>
                    ` : ''}
                    <div class="text-muted" style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
                        ${timeAgo}
                    </div>
                </div>
                
                ${audioAttribution}
            `;
            
            container.appendChild(postDiv);
            
            // Attach event listeners
            attachPostEventListeners(postDiv, post);
        });
    }
    
    function attachPostEventListeners(postElement, post) {
        // Like button
        const likeBtn = postElement.querySelector('.like-btn');
        if (likeBtn) {
            likeBtn.addEventListener('click', async function() {
                await toggleLike(post.id, this);
            });
        }
        
        // Comment button
        const commentBtn = postElement.querySelector('.comment-btn');
        if (commentBtn) {
            commentBtn.addEventListener('click', function() {
                showCommentsModal(post.id);
            });
        }
        
        // View all comments button
        const viewCommentsBtn = postElement.querySelector('.view-comments-btn');
        if (viewCommentsBtn) {
            viewCommentsBtn.addEventListener('click', function() {
                showCommentsModal(post.id);
            });
        }
        
        // Share button
        const shareBtn = postElement.querySelector('.share-btn');
        if (shareBtn) {
            shareBtn.addEventListener('click', function() {
                sharePost(post);
            });
        }
    }
    
    async function toggleLike(postId, buttonElement) {
        try {
            const response = await fetch(`/world-feed/posts/${postId}/like`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            
            if (response.ok) {
                const icon = buttonElement.querySelector('i');
                const likesText = buttonElement.closest('.px-3').querySelector('strong');
                
                if (icon.classList.contains('bi-heart-fill')) {
                    icon.classList.remove('bi-heart-fill', 'text-danger');
                    icon.classList.add('bi-heart');
                    const currentLikes = parseInt(likesText.textContent) || 0;
                    likesText.textContent = `${Math.max(0, currentLikes - 1)} likes`;
                } else {
                    icon.classList.remove('bi-heart');
                    icon.classList.add('bi-heart-fill', 'text-danger');
                    const currentLikes = parseInt(likesText.textContent) || 0;
                    likesText.textContent = `${currentLikes + 1} likes`;
                }
            }
        } catch (error) {
            console.error('Error toggling like:', error);
        }
    }
    
    async function showCommentsModal(postId) {
        try {
            // Load comments
            const response = await fetch(`/world-feed/posts/${postId}/comments`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            // Handle paginated response
            let comments = [];
            if (data.data && Array.isArray(data.data)) {
                comments = data.data;
            } else if (data.data && data.data.data && Array.isArray(data.data.data)) {
                comments = data.data.data;
            } else if (Array.isArray(data)) {
                comments = data;
            }
            
            // Create comments modal HTML
            const modalHtml = `
                <div class="modal fade" id="commentsModal${postId}" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content" style="max-height: 80vh;">
                            <div class="modal-header">
                                <h5 class="modal-title">Comments</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body overflow-auto" style="max-height: 60vh;">
                                <div id="comments-list-${postId}">
                                    ${comments.length === 0 ? '<p class="text-muted text-center py-3">No comments yet</p>' : ''}
                                    ${comments.map(comment => {
                                        let avatarUrl = comment.user?.avatar_url || '/images/default-avatar.png';
                                        if (avatarUrl && avatarUrl.startsWith('/') && !avatarUrl.startsWith('//')) {
                                            avatarUrl = window.location.origin + avatarUrl;
                                        }
                                        return `
                                        <div class="mb-3 pb-3 border-bottom">
                                            <div class="d-flex">
                                                <img src="${escapeHtml(avatarUrl)}" 
                                                     class="rounded-circle me-2" 
                                                     style="width: 32px; height: 32px; object-fit: cover;"
                                                     onerror="this.src='${window.location.origin}/images/default-avatar.png'">
                                                <div class="flex-grow-1">
                                                    <strong>${escapeHtml(comment.user?.name || 'Unknown')}</strong>
                                                    <p class="mb-0">${escapeHtml(comment.comment || comment.body || '')}</p>
                                                    <small class="text-muted">${getTimeAgo(new Date(comment.created_at || comment.created_at))}</small>
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                    }).join('')}
                                </div>
                            </div>
                            <div class="modal-footer">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="comment-input-${postId}" placeholder="Add a comment...">
                                    <button class="btn btn-wa" type="button" data-post-id="${postId}">Post</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById(`commentsModal${postId}`);
            if (existingModal) existingModal.remove();
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById(`commentsModal${postId}`));
            modal.show();
            
            // Handle Enter key in comment input
            const commentInput = document.getElementById(`comment-input-${postId}`);
            const postBtn = document.querySelector(`#commentsModal${postId} .btn-wa[data-post-id="${postId}"]`);
            
            if (commentInput) {
                commentInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        addCommentToPost(postId);
                    }
                });
            }
            
            if (postBtn) {
                postBtn.addEventListener('click', function() {
                    addCommentToPost(postId);
                });
            }
            
            // Clean up modal when hidden
            document.getElementById(`commentsModal${postId}`).addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
            
        } catch (error) {
            console.error('Error loading comments:', error);
            alert('Failed to load comments');
        }
    }
    
    async function addCommentToPost(postId) {
        const input = document.getElementById(`comment-input-${postId}`);
        const commentText = input.value.trim();
        
        if (!commentText) return;
        
        try {
            const response = await fetch(`/world-feed/posts/${postId}/comments`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ comment: commentText })
            });
            
            if (response.ok) {
                input.value = '';
                // Close modal and reload posts to update comment count
                const modalElement = document.getElementById(`commentsModal${postId}`);
                if (modalElement) {
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) modal.hide();
                }
                // Refresh posts to update comment count
                loadPosts();
                showToast('Comment added!', 'success');
            } else {
                const data = await response.json();
                alert(data.message || 'Failed to add comment');
            }
        } catch (error) {
            console.error('Error adding comment:', error);
            alert('Failed to add comment');
        }
    }
    
    function sharePost(post) {
        if (navigator.share) {
            navigator.share({
                title: 'Check out this post on GekyChat',
                text: post.caption || '',
                url: window.location.origin + '/wf/' + (post.share_code || post.id)
            }).catch(err => console.log('Error sharing:', err));
        } else {
            // Fallback: copy to clipboard
            const url = window.location.origin + '/wf/' + (post.share_code || post.id);
            navigator.clipboard.writeText(url).then(() => {
                showToast('Link copied to clipboard!', 'success');
            });
        }
    }
    
    function getTimeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        if (seconds < 60) return 'JUST NOW';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes}M AGO`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}H AGO`;
        const days = Math.floor(hours / 24);
        if (days < 7) return `${days}D AGO`;
        const weeks = Math.floor(days / 7);
        if (weeks < 4) return `${weeks}W AGO`;
        const months = Math.floor(days / 30);
        return `${months}MO AGO`;
    }
    
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} position-fixed bottom-0 start-50 translate-middle-x mb-3`;
        toast.style.zIndex = '9999';
        toast.style.minWidth = '300px';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
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
    
    // Media input change handler - show audio section for videos
    const postMediaInput = document.getElementById('post-media-input');
    if (postMediaInput) {
        postMediaInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const audioSection = document.getElementById('audio-section');
            
            if (file && file.type.startsWith('video/')) {
                // Show audio section for videos
                if (audioSection) {
                    audioSection.style.display = 'block';
                }
            } else {
                // Hide audio section for images
                if (audioSection) {
                    audioSection.style.display = 'none';
                    // Clear audio selection when switching to image
                    document.getElementById('audio-selection-empty').style.display = 'block';
                    document.getElementById('audio-selection-filled').style.display = 'none';
                    document.getElementById('audio-id-input').value = '';
                    document.getElementById('audio-attribution-alert').style.display = 'none';
                    localStorage.removeItem('selectedAudio');
                }
            }
        });
    }
    
    // Audio selection handlers
    let audioCheckInterval = null;
    
    document.getElementById('add-audio-btn')?.addEventListener('click', () => {
        const popup = window.open('/audio/browse', 'AudioBrowser', 'width=800,height=600,scrollbars=yes');
        if (popup) {
            // Poll for selection if popup opened successfully
            if (audioCheckInterval) clearInterval(audioCheckInterval);
            audioCheckInterval = setInterval(() => {
                const selectedAudio = localStorage.getItem('selectedAudio');
                if (selectedAudio && popup.closed) {
                    try {
                        const audio = JSON.parse(selectedAudio);
                        selectAudioForPost(audio);
                        localStorage.removeItem('selectedAudio');
                        clearInterval(audioCheckInterval);
                        audioCheckInterval = null;
                    } catch (err) {
                        console.error('Error parsing selected audio:', err);
                    }
                }
            }, 500);
        }
    });
    
    document.getElementById('remove-audio-btn')?.addEventListener('click', () => {
        document.getElementById('audio-selection-empty').style.display = 'block';
        document.getElementById('audio-selection-filled').style.display = 'none';
        document.getElementById('audio-id-input').value = '';
        document.getElementById('audio-attribution-alert').style.display = 'none';
        localStorage.removeItem('selectedAudio');
    });
    
    document.getElementById('audio-volume-slider')?.addEventListener('input', function(e) {
        const volume = e.target.value;
        document.getElementById('audio-volume-label').textContent = volume + '%';
        document.getElementById('audio-volume-input').value = volume;
    });
    
    // Listen for audio selection from popup (multiple methods for compatibility)
    
    // Method 1: Listen for postMessage from popup
    window.addEventListener('message', function(event) {
        if (event.data && event.data.type === 'audioSelected') {
            selectAudioForPost(event.data.audio);
            if (audioCheckInterval) {
                clearInterval(audioCheckInterval);
                audioCheckInterval = null;
            }
        }
    });
    
    // Method 2: Listen for storage events (cross-window)
    window.addEventListener('storage', function(e) {
        if (e.key === 'selectedAudio' && e.newValue) {
            try {
                const audio = JSON.parse(e.newValue);
                selectAudioForPost(audio);
                localStorage.removeItem('selectedAudio');
                if (audioCheckInterval) {
                    clearInterval(audioCheckInterval);
                    audioCheckInterval = null;
                }
            } catch (err) {
                console.error('Error parsing selected audio:', err);
            }
        }
    });
    
    function selectAudioForPost(audio) {
        const selectedAudioName = document.getElementById('selected-audio-name');
        const selectedAudioArtist = document.getElementById('selected-audio-artist');
        const audioIdInput = document.getElementById('audio-id-input');
        const audioAttributionText = document.getElementById('audio-attribution-text');
        const audioAttributionAlert = document.getElementById('audio-attribution-alert');
        const audioSelectionEmpty = document.getElementById('audio-selection-empty');
        const audioSelectionFilled = document.getElementById('audio-selection-filled');
        
        if (!selectedAudioName || !audioIdInput) {
            console.error('Audio selection elements not found');
            return;
        }
        
        selectedAudioName.textContent = audio.name || 'Unknown';
        selectedAudioArtist.textContent = 'by ' + (audio.freesound_username || audio.username || 'Unknown');
        audioIdInput.value = audio.id;
        
        if (audio.attribution_required && audio.attribution_text) {
            audioAttributionText.textContent = audio.attribution_text;
            audioAttributionAlert.style.display = 'block';
        } else {
            audioAttributionAlert.style.display = 'none';
        }
        
        audioSelectionEmpty.style.display = 'none';
        audioSelectionFilled.style.display = 'block';
    }
    
    // Create post handlers
    const createPostModal = document.getElementById('createPostModal');
    
    // When modal is shown, reset audio section and attach file input handler
    createPostModal?.addEventListener('show.bs.modal', function() {
        // Reset audio section
        const audioSection = document.getElementById('audio-section');
        if (audioSection) {
            audioSection.style.display = 'none';
            document.getElementById('audio-selection-empty').style.display = 'block';
            document.getElementById('audio-selection-filled').style.display = 'none';
            document.getElementById('audio-id-input').value = '';
            document.getElementById('audio-volume-input').value = '100';
            document.getElementById('audio-volume-slider').value = '100';
            document.getElementById('audio-volume-label').textContent = '100%';
            document.getElementById('audio-attribution-alert').style.display = 'none';
        }
        
        // Clear any selected audio from localStorage
        localStorage.removeItem('selectedAudio');
        
        // Clear file input
        const fileInput = document.getElementById('post-media-input');
        if (fileInput) {
            fileInput.value = '';
            
            // Ensure event listener is attached (in case modal was removed/recreated)
            // Remove existing listener and reattach
            const newInput = fileInput.cloneNode(true);
            fileInput.parentNode.replaceChild(newInput, fileInput);
            
            // Reattach change handler
            newInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                const audioSection = document.getElementById('audio-section');
                
                if (file && file.type.startsWith('video/')) {
                    // Show audio section for videos
                    if (audioSection) {
                        audioSection.style.display = 'block';
                        console.log('Audio section shown for video');
                    }
                } else {
                    // Hide audio section for images
                    if (audioSection) {
                        audioSection.style.display = 'none';
                        document.getElementById('audio-selection-empty').style.display = 'block';
                        document.getElementById('audio-selection-filled').style.display = 'none';
                        document.getElementById('audio-id-input').value = '';
                        document.getElementById('audio-attribution-alert').style.display = 'none';
                        localStorage.removeItem('selectedAudio');
                    }
                }
            });
        }
    });
    
    document.getElementById('create-post-btn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(createPostModal);
        modal.show();
    });
    
    document.getElementById('create-first-post-btn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(createPostModal);
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
                bootstrap.Modal.getInstance(createPostModal).hide();
                e.target.reset();
                // Reset audio section
                const audioSection = document.getElementById('audio-section');
                if (audioSection) {
                    audioSection.style.display = 'none';
                    document.getElementById('audio-selection-empty').style.display = 'block';
                    document.getElementById('audio-selection-filled').style.display = 'none';
                    document.getElementById('audio-id-input').value = '';
                    document.getElementById('audio-volume-input').value = '100';
                    document.getElementById('audio-volume-slider').value = '100';
                    document.getElementById('audio-volume-label').textContent = '100%';
                    document.getElementById('audio-attribution-alert').style.display = 'none';
                }
                // Clear file input
                const fileInput = document.getElementById('post-media-input');
                if (fileInput) fileInput.value = '';
                loadPosts(1);
                showToast('Post created successfully!', 'success');
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
