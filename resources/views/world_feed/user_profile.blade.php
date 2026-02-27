@extends('layouts.app')

@section('title', ($profileUser->name ?? $profileUser->username ?? 'User') . ' - World Feed')

@push('styles')
<style>
    .profile-container {
        background-color: var(--bg, #fafafa);
        min-height: calc(100vh - 60px);
    }
    
    [data-theme="dark"] .profile-container {
        background-color: var(--bg, #111B21);
    }
    
    .profile-header {
        background: var(--card, white);
        border-bottom: 1px solid var(--border, #dbdbdb);
        padding: 24px 16px;
    }
    
    [data-theme="dark"] .profile-header {
        background: var(--card, #202C33);
        border-color: var(--border, #2A3942);
    }
    
    .profile-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--wa-green, #25d366);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .profile-name {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text, #111827);
        margin: 0;
    }
    
    [data-theme="dark"] .profile-name {
        color: var(--text, #E5E7EB);
    }
    
    .profile-username {
        font-size: 1rem;
        color: var(--wa-muted, #667781);
        margin: 0;
    }
    
    .profile-about {
        color: var(--text, #111827);
        font-size: 0.95rem;
        margin-top: 12px;
        max-width: 400px;
    }
    
    [data-theme="dark"] .profile-about {
        color: var(--text, #E5E7EB);
    }
    
    .profile-stats {
        display: flex;
        gap: 32px;
        margin-top: 16px;
    }
    
    .stat-item {
        text-align: center;
        cursor: pointer;
        transition: opacity 0.2s;
    }
    
    .stat-item:hover {
        opacity: 0.8;
    }
    
    .stat-number {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text, #111827);
        display: block;
    }
    
    [data-theme="dark"] .stat-number {
        color: var(--text, #E5E7EB);
    }
    
    .stat-label {
        font-size: 0.85rem;
        color: var(--wa-muted, #667781);
    }
    
    .profile-actions {
        display: flex;
        gap: 12px;
        margin-top: 20px;
    }
    
    .btn-follow {
        background: var(--wa-green, #25d366);
        color: #062a1f;
        border: none;
        padding: 10px 32px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.2s;
    }
    
    .btn-follow:hover {
        filter: brightness(1.05);
        transform: translateY(-1px);
    }
    
    .btn-follow.following {
        background: var(--input-bg, #f0f2f5);
        color: var(--text, #111827);
    }
    
    [data-theme="dark"] .btn-follow.following {
        background: var(--input-bg, #2A3942);
        color: var(--text, #E5E7EB);
    }
    
    .btn-message {
        background: var(--input-bg, #f0f2f5);
        color: var(--text, #111827);
        border: none;
        padding: 10px 24px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.2s;
    }
    
    [data-theme="dark"] .btn-message {
        background: var(--input-bg, #2A3942);
        color: var(--text, #E5E7EB);
    }
    
    .btn-message:hover {
        filter: brightness(0.95);
    }
    
    .btn-edit-profile {
        background: var(--input-bg, #f0f2f5);
        color: var(--text, #111827);
        border: none;
        padding: 10px 24px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.2s;
    }
    
    [data-theme="dark"] .btn-edit-profile {
        background: var(--input-bg, #2A3942);
        color: var(--text, #E5E7EB);
    }
    
    .btn-edit-profile:hover {
        filter: brightness(0.95);
    }
    
    .profile-tabs {
        display: flex;
        border-bottom: 1px solid var(--border, #dbdbdb);
        background: var(--card, white);
    }
    
    [data-theme="dark"] .profile-tabs {
        background: var(--card, #202C33);
        border-color: var(--border, #2A3942);
    }
    
    .profile-tab {
        flex: 1;
        text-align: center;
        padding: 16px;
        cursor: pointer;
        border-bottom: 2px solid transparent;
        color: var(--wa-muted, #667781);
        font-weight: 500;
        transition: all 0.2s;
    }
    
    .profile-tab:hover {
        color: var(--text, #111827);
    }
    
    [data-theme="dark"] .profile-tab:hover {
        color: var(--text, #E5E7EB);
    }
    
    .profile-tab.active {
        border-bottom-color: var(--wa-green, #25d366);
        color: var(--text, #111827);
    }
    
    [data-theme="dark"] .profile-tab.active {
        color: var(--text, #E5E7EB);
    }
    
    .posts-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 4px;
        padding: 4px;
    }
    
    .post-thumbnail {
        aspect-ratio: 1;
        overflow: hidden;
        cursor: pointer;
        position: relative;
        background: var(--input-bg, #f0f2f5);
    }
    
    [data-theme="dark"] .post-thumbnail {
        background: var(--input-bg, #2A3942);
    }
    
    .post-thumbnail img,
    .post-thumbnail video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.2s;
    }
    
    .post-thumbnail:hover img,
    .post-thumbnail:hover video {
        transform: scale(1.05);
    }
    
    .post-thumbnail .overlay {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 16px;
        opacity: 0;
        transition: opacity 0.2s;
    }
    
    .post-thumbnail:hover .overlay {
        opacity: 1;
    }
    
    .overlay-stat {
        display: flex;
        align-items: center;
        gap: 6px;
        color: white;
        font-weight: 600;
    }
    
    .video-indicator {
        position: absolute;
        top: 8px;
        right: 8px;
        color: white;
        font-size: 1.25rem;
        text-shadow: 0 1px 3px rgba(0,0,0,0.5);
    }
    
    .empty-posts {
        text-align: center;
        padding: 60px 20px;
        color: var(--wa-muted, #667781);
    }
    
    .empty-posts i {
        font-size: 4rem;
        margin-bottom: 16px;
        opacity: 0.5;
    }
    
    @media (max-width: 768px) {
        .profile-header {
            padding: 16px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
        }
        
        .profile-name {
            font-size: 1.25rem;
        }
        
        .profile-stats {
            gap: 20px;
        }
        
        .stat-number {
            font-size: 1.1rem;
        }
        
        .profile-actions {
            flex-wrap: wrap;
        }
        
        .btn-follow, .btn-message, .btn-edit-profile {
            flex: 1;
            min-width: 100px;
        }
        
        .posts-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 2px;
            padding: 2px;
        }
    }
    
    .followers-modal .modal-body {
        max-height: 60vh;
        overflow-y: auto;
    }
    
    .follower-item {
        display: flex;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid var(--border, #dbdbdb);
    }
    
    [data-theme="dark"] .follower-item {
        border-color: var(--border, #2A3942);
    }
    
    .follower-item:last-child {
        border-bottom: none;
    }
    
    .follower-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 12px;
    }
    
    .follower-info {
        flex: 1;
    }
    
    .follower-name {
        font-weight: 600;
        color: var(--text, #111827);
    }
    
    [data-theme="dark"] .follower-name {
        color: var(--text, #E5E7EB);
    }
    
    .follower-username {
        font-size: 0.85rem;
        color: var(--wa-muted, #667781);
    }
    
    .btn-follow-sm {
        background: var(--wa-green, #25d366);
        color: #062a1f;
        border: none;
        padding: 6px 16px;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .btn-follow-sm.following {
        background: var(--input-bg, #f0f2f5);
        color: var(--text, #111827);
    }
    
    [data-theme="dark"] .btn-follow-sm.following {
        background: var(--input-bg, #2A3942);
        color: var(--text, #E5E7EB);
    }
    
    /* Common Groups Section */
    .common-groups-section {
        background: var(--card, white);
        border-bottom: 1px solid var(--border, #dbdbdb);
        padding: 16px;
    }
    
    [data-theme="dark"] .common-groups-section {
        background: var(--card, #202C33);
        border-color: var(--border, #2A3942);
    }
    
    .common-groups-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        color: var(--text, #111827);
        font-weight: 600;
    }
    
    [data-theme="dark"] .common-groups-header {
        color: var(--text, #E5E7EB);
    }
    
    .common-groups-header .badge {
        background: var(--wa-green, #25d366);
        color: #062a1f;
    }
    
    .common-groups-scroll {
        display: flex;
        gap: 12px;
        overflow-x: auto;
        padding-bottom: 8px;
        scrollbar-width: thin;
    }
    
    .common-groups-scroll::-webkit-scrollbar {
        height: 4px;
    }
    
    .common-groups-scroll::-webkit-scrollbar-thumb {
        background: var(--border, #dbdbdb);
        border-radius: 2px;
    }
    
    .common-group-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-decoration: none;
        padding: 12px;
        border-radius: 12px;
        background: var(--input-bg, #f0f2f5);
        min-width: 100px;
        transition: all 0.2s;
    }
    
    [data-theme="dark"] .common-group-card {
        background: var(--input-bg, #2A3942);
    }
    
    .common-group-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .common-group-card .group-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        margin-bottom: 8px;
    }
    
    .common-group-card .group-avatar-placeholder {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        font-weight: 600;
        color: white;
        margin-bottom: 8px;
    }
    
    .common-group-card .group-name {
        font-size: 0.8rem;
        font-weight: 500;
        color: var(--text, #111827);
        text-align: center;
        max-width: 80px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    [data-theme="dark"] .common-group-card .group-name {
        color: var(--text, #E5E7EB);
    }
    
    .common-group-card .group-members {
        font-size: 0.7rem;
        color: var(--wa-muted, #667781);
    }
    
    .common-groups-empty {
        text-align: center;
        color: var(--wa-muted, #667781);
        padding: 8px;
        font-size: 0.9rem;
    }
</style>
@endpush

@section('content')
<div class="profile-container">
    <div class="container-fluid px-0">
        <div class="row justify-content-center g-0">
            <div class="col-12 col-lg-8 col-xl-6">
                
                {{-- Profile Header --}}
                <div class="profile-header">
                    <div class="d-flex flex-column flex-md-row align-items-center align-items-md-start gap-4">
                        <img src="{{ $avatarUrl }}" 
                             alt="{{ $profileUser->name ?? 'User' }}" 
                             class="profile-avatar"
                             onerror="this.src='{{ asset('icons/icon-192x192.png') }}'">
                        
                        <div class="flex-grow-1 text-center text-md-start">
                            <h1 class="profile-name">{{ $profileUser->name ?? 'User' }}</h1>
                            @if($profileUser->username)
                                <p class="profile-username">@{{ $profileUser->username }}</p>
                            @endif
                            
                            @if($profileUser->about)
                                <p class="profile-about">{{ $profileUser->about }}</p>
                            @endif
                            
                            <div class="profile-stats">
                                <div class="stat-item" data-bs-toggle="modal" data-bs-target="#postsModal">
                                    <span class="stat-number">{{ number_format($postsCount) }}</span>
                                    <span class="stat-label">Posts</span>
                                </div>
                                <div class="stat-item" data-bs-toggle="modal" data-bs-target="#followersModal">
                                    <span class="stat-number" id="followers-count">{{ number_format($followersCount) }}</span>
                                    <span class="stat-label">Followers</span>
                                </div>
                                <div class="stat-item" data-bs-toggle="modal" data-bs-target="#followingModal">
                                    <span class="stat-number">{{ number_format($followingCount) }}</span>
                                    <span class="stat-label">Following</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number">{{ number_format($totalLikes) }}</span>
                                    <span class="stat-label">Likes</span>
                                </div>
                            </div>
                            
                            <div class="profile-actions">
                                @if($isOwnProfile)
                                    <a href="{{ route('profile.edit') }}" class="btn btn-edit-profile">
                                        <i class="bi bi-pencil me-2"></i>Edit Profile
                                    </a>
                                @else
                                    <button class="btn btn-follow {{ $isFollowing ? 'following' : '' }}" 
                                            id="follow-btn"
                                            data-user-id="{{ $profileUser->id }}"
                                            data-is-following="{{ $isFollowing ? 'true' : 'false' }}">
                                        <i class="bi {{ $isFollowing ? 'bi-person-check' : 'bi-person-plus' }} me-2"></i>
                                        <span>{{ $isFollowing ? 'Following' : 'Follow' }}</span>
                                    </button>
                                    <a href="{{ route('chat.index') }}?user={{ $profileUser->id }}" class="btn btn-message">
                                        <i class="bi bi-chat-dots me-2"></i>Message
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                
                {{-- Common Groups Section (only show for other users' profiles) --}}
                @if(!$isOwnProfile)
                <div class="common-groups-section" id="common-groups-section" style="display: none;">
                    <div class="common-groups-header">
                        <i class="bi bi-people"></i>
                        <span>Groups in Common</span>
                        <span class="badge" id="common-groups-count">0</span>
                    </div>
                    <div class="common-groups-scroll" id="common-groups-list">
                        {{-- Groups will be loaded here --}}
                    </div>
                    <div class="common-groups-empty" id="common-groups-empty" style="display: none;">
                        <i class="bi bi-people me-2"></i>No groups in common
                    </div>
                </div>
                @endif
                
                {{-- Tabs --}}
                <div class="profile-tabs">
                    <div class="profile-tab active" data-tab="posts">
                        <i class="bi bi-grid-3x3 me-2"></i>Posts
                    </div>
                    <div class="profile-tab" data-tab="videos">
                        <i class="bi bi-play-btn me-2"></i>Videos
                    </div>
                    <div class="profile-tab" data-tab="liked">
                        <i class="bi bi-heart me-2"></i>Liked
                    </div>
                </div>
                
                {{-- Posts Grid --}}
                <div id="posts-container">
                    <div class="posts-grid" id="posts-grid">
                        {{-- Posts will be loaded here --}}
                    </div>
                    
                    <div class="empty-posts" id="empty-posts" style="display: none;">
                        <i class="bi bi-camera"></i>
                        <h5>No Posts Yet</h5>
                        <p class="text-muted">When {{ $isOwnProfile ? 'you share' : 'this user shares' }} posts, they'll appear here.</p>
                    </div>
                    
                    <div class="text-center py-4" id="posts-loader">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

{{-- Followers Modal --}}
<div class="modal fade followers-modal" id="followersModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Followers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="followers-list">
                <div class="text-center py-4">
                    <div class="spinner-border text-success" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Following Modal --}}
<div class="modal fade followers-modal" id="followingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Following</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="following-list">
                <div class="text-center py-4">
                    <div class="spinner-border text-success" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Post View Modal --}}
<div class="modal fade" id="postViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Post</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="post-view-content">
                {{-- Post content will be loaded here --}}
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const profileUserId = {{ $profileUser->id }};
    const isOwnProfile = {{ $isOwnProfile ? 'true' : 'false' }};
    let currentTab = 'posts';
    let currentPage = 1;
    let isLoading = false;
    let hasMore = true;
    
    // Load posts
    async function loadPosts(page = 1, append = false) {
        if (isLoading) return;
        isLoading = true;
        
        const loader = document.getElementById('posts-loader');
        const grid = document.getElementById('posts-grid');
        const empty = document.getElementById('empty-posts');
        
        if (!append) {
            loader.style.display = 'block';
            grid.innerHTML = '';
        }
        
        try {
            let url = `/world-feed/posts?creator_id=${profileUserId}&page=${page}&per_page=18`;
            
            if (currentTab === 'videos') {
                url += '&type=video';
            }
            
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            let posts = [];
            
            if (Array.isArray(data.data)) {
                posts = data.data;
            } else if (data.data && Array.isArray(data.data.data)) {
                posts = data.data.data;
            }
            
            // Filter for videos tab
            if (currentTab === 'videos') {
                posts = posts.filter(p => p.type === 'video' || p.media_type === 'video' || 
                    (p.media_url && p.media_url.match(/\.(mp4|webm|ogg|mov)$/i)));
            }
            
            loader.style.display = 'none';
            
            if (posts.length === 0 && page === 1) {
                empty.style.display = 'block';
                grid.style.display = 'none';
                hasMore = false;
            } else {
                empty.style.display = 'none';
                grid.style.display = 'grid';
                renderPosts(posts, append);
                hasMore = posts.length >= 18;
            }
            
            currentPage = page;
        } catch (error) {
            console.error('Error loading posts:', error);
            loader.innerHTML = '<div class="alert alert-danger">Failed to load posts</div>';
        } finally {
            isLoading = false;
        }
    }
    
    function renderPosts(posts, append = false) {
        const grid = document.getElementById('posts-grid');
        
        if (!append) {
            grid.innerHTML = '';
        }
        
        posts.forEach(post => {
            const isVideo = post.type === 'video' || post.media_type === 'video' || 
                (post.media_url && post.media_url.match(/\.(mp4|webm|ogg|mov)$/i));
            
            const thumbnail = post.thumbnail_url || post.media_url;
            
            const div = document.createElement('div');
            div.className = 'post-thumbnail';
            div.dataset.postId = post.id;
            div.innerHTML = `
                ${isVideo ? `
                    <video src="${escapeHtml(post.media_url)}" muted></video>
                    <span class="video-indicator"><i class="bi bi-play-fill"></i></span>
                ` : `
                    <img src="${escapeHtml(thumbnail)}" alt="Post" loading="lazy" 
                         onerror="this.src='{{ asset('icons/icon-192x192.png') }}'">
                `}
                <div class="overlay">
                    <span class="overlay-stat">
                        <i class="bi bi-heart-fill"></i>
                        ${formatNumber(post.likes_count || 0)}
                    </span>
                    <span class="overlay-stat">
                        <i class="bi bi-chat-fill"></i>
                        ${formatNumber(post.comments_count || 0)}
                    </span>
                </div>
            `;
            
            div.addEventListener('click', () => viewPost(post));
            grid.appendChild(div);
        });
    }
    
    function viewPost(post) {
        // Redirect to world feed with post highlighted
        window.location.href = `/world-feed?post=${post.id}`;
    }
    
    // Tab switching
    document.querySelectorAll('.profile-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentTab = this.dataset.tab;
            currentPage = 1;
            hasMore = true;
            
            if (currentTab === 'liked') {
                // Show message for liked tab
                document.getElementById('posts-grid').style.display = 'none';
                document.getElementById('empty-posts').style.display = 'block';
                document.getElementById('empty-posts').innerHTML = `
                    <i class="bi bi-heart"></i>
                    <h5>Liked Posts</h5>
                    <p class="text-muted">Liked posts are private.</p>
                `;
                document.getElementById('posts-loader').style.display = 'none';
            } else {
                loadPosts(1);
            }
        });
    });
    
    // Follow button
    const followBtn = document.getElementById('follow-btn');
    if (followBtn) {
        followBtn.addEventListener('click', async function() {
            const userId = this.dataset.userId;
            const isFollowing = this.dataset.isFollowing === 'true';
            
            try {
                const response = await fetch(`/world-feed/creators/${userId}/follow`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    const nowFollowing = data.is_following;
                    this.dataset.isFollowing = nowFollowing ? 'true' : 'false';
                    
                    if (nowFollowing) {
                        this.classList.add('following');
                        this.innerHTML = '<i class="bi bi-person-check me-2"></i><span>Following</span>';
                    } else {
                        this.classList.remove('following');
                        this.innerHTML = '<i class="bi bi-person-plus me-2"></i><span>Follow</span>';
                    }
                    
                    // Update follower count
                    const countEl = document.getElementById('followers-count');
                    if (countEl) {
                        let count = parseInt(countEl.textContent.replace(/,/g, '')) || 0;
                        count = nowFollowing ? count + 1 : Math.max(0, count - 1);
                        countEl.textContent = formatNumber(count);
                    }
                }
            } catch (error) {
                console.error('Error toggling follow:', error);
            }
        });
    }
    
    // Load followers modal
    document.getElementById('followersModal')?.addEventListener('show.bs.modal', async function() {
        const list = document.getElementById('followers-list');
        list.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success" role="status"></div></div>';
        
        try {
            const response = await fetch(`/world-feed/users/${profileUserId}/followers`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            const users = data.data || [];
            
            if (users.length === 0) {
                list.innerHTML = '<p class="text-center text-muted py-4">No followers yet</p>';
            } else {
                list.innerHTML = users.map(user => renderFollowerItem(user)).join('');
                attachFollowListeners(list);
            }
        } catch (error) {
            list.innerHTML = '<p class="text-center text-danger py-4">Failed to load followers</p>';
        }
    });
    
    // Load following modal
    document.getElementById('followingModal')?.addEventListener('show.bs.modal', async function() {
        const list = document.getElementById('following-list');
        list.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success" role="status"></div></div>';
        
        try {
            const response = await fetch(`/world-feed/users/${profileUserId}/following`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            const users = data.data || [];
            
            if (users.length === 0) {
                list.innerHTML = '<p class="text-center text-muted py-4">Not following anyone yet</p>';
            } else {
                list.innerHTML = users.map(user => renderFollowerItem(user)).join('');
                attachFollowListeners(list);
            }
        } catch (error) {
            list.innerHTML = '<p class="text-center text-danger py-4">Failed to load following</p>';
        }
    });
    
    function renderFollowerItem(user) {
        const currentUserId = parseInt(document.querySelector('meta[name="current-user-id"]')?.content || '0');
        const isCurrentUser = user.id === currentUserId;
        
        return `
            <div class="follower-item">
                <a href="/world-feed/user/${user.id}">
                    <img src="${escapeHtml(user.avatar_url || '{{ asset('icons/icon-192x192.png') }}')}" 
                         alt="${escapeHtml(user.name)}" 
                         class="follower-avatar"
                         onerror="this.src='{{ asset('icons/icon-192x192.png') }}'">
                </a>
                <div class="follower-info">
                    <a href="/world-feed/user/${user.id}" class="text-decoration-none">
                        <div class="follower-name">${escapeHtml(user.name || 'User')}</div>
                        ${user.username ? `<div class="follower-username">@${escapeHtml(user.username)}</div>` : ''}
                    </a>
                </div>
                ${!isCurrentUser ? `
                    <button class="btn btn-follow-sm ${user.is_following ? 'following' : ''}" 
                            data-user-id="${user.id}"
                            data-is-following="${user.is_following ? 'true' : 'false'}">
                        ${user.is_following ? 'Following' : 'Follow'}
                    </button>
                ` : ''}
            </div>
        `;
    }
    
    function attachFollowListeners(container) {
        container.querySelectorAll('.btn-follow-sm').forEach(btn => {
            btn.addEventListener('click', async function() {
                const userId = this.dataset.userId;
                
                try {
                    const response = await fetch(`/world-feed/creators/${userId}/follow`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (response.ok) {
                        const nowFollowing = data.is_following;
                        this.dataset.isFollowing = nowFollowing ? 'true' : 'false';
                        this.textContent = nowFollowing ? 'Following' : 'Follow';
                        this.classList.toggle('following', nowFollowing);
                    }
                } catch (error) {
                    console.error('Error toggling follow:', error);
                }
            });
        });
    }
    
    // Infinite scroll
    window.addEventListener('scroll', () => {
        if (isLoading || !hasMore) return;
        
        const scrollTop = window.scrollY;
        const windowHeight = window.innerHeight;
        const docHeight = document.documentElement.scrollHeight;
        
        if (scrollTop + windowHeight >= docHeight - 500) {
            loadPosts(currentPage + 1, true);
        }
    });
    
    // Utility functions
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    }
    
    // Load common groups
    async function loadCommonGroups() {
        if (isOwnProfile) return;
        
        const section = document.getElementById('common-groups-section');
        const list = document.getElementById('common-groups-list');
        const empty = document.getElementById('common-groups-empty');
        const countBadge = document.getElementById('common-groups-count');
        
        if (!section) return;
        
        try {
            const response = await fetch(`/api/v1/users/${profileUserId}/common-groups`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) throw new Error('Failed to fetch');
            
            const data = await response.json();
            const groups = data.data || [];
            
            countBadge.textContent = groups.length;
            
            if (groups.length === 0) {
                section.style.display = 'none';
                return;
            }
            
            section.style.display = 'block';
            empty.style.display = 'none';
            
            list.innerHTML = groups.map(group => {
                const avatarColors = ['#10B981', '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#EF4444', '#06B6D4'];
                const colorIndex = (group.name || '').charCodeAt(0) % avatarColors.length;
                const bgColor = avatarColors[colorIndex];
                const initial = (group.name || '?').charAt(0).toUpperCase();
                
                const avatarHtml = group.avatar_url 
                    ? `<img src="${escapeHtml(group.avatar_url)}" class="group-avatar" alt="${escapeHtml(group.name)}" 
                           onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                       <div class="group-avatar-placeholder" style="display: none; background: ${bgColor};">${initial}</div>`
                    : `<div class="group-avatar-placeholder" style="background: ${bgColor};">${initial}</div>`;
                
                return `
                    <a href="/g/${escapeHtml(group.slug)}" class="common-group-card">
                        ${avatarHtml}
                        <div class="group-name" title="${escapeHtml(group.name)}">${escapeHtml(group.name)}</div>
                        <div class="group-members">${group.member_count || 0} members</div>
                    </a>
                `;
            }).join('');
            
        } catch (error) {
            console.error('Error loading common groups:', error);
            section.style.display = 'none';
        }
    }
    
    // Initial load
    loadPosts(1);
    loadCommonGroups();
});
</script>
@endpush
